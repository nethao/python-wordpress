import os
import json
import hashlib
import asyncio
from typing import List, Dict, Optional
from openai import OpenAI
import requests
import time

class DeepSeekAudit:
    def __init__(self, api_key: str = None):
        self.api_key = api_key or os.getenv("DEEPSEEK_API_KEY", "")
        self.client = OpenAI(
            api_key=self.api_key,
            base_url="https://api.deepseek.com"
        ) if self.api_key else None
        self.cache = {}
        self.cache_expire = 3600  # 1小时缓存
        
    def _get_cache_key(self, content: str, strict_level: int) -> str:
        """生成缓存键"""
        return hashlib.md5(f"{content}:{strict_level}".encode()).hexdigest()
    
    def _is_cache_valid(self, cache_data: dict) -> bool:
        """检查缓存是否有效"""
        return time.time() - cache_data.get('timestamp', 0) < self.cache_expire
    
    def _get_audit_prompt(self, content: str, strict_level: int) -> str:
        """构建审核提示词"""
        return f"""你是一个专业的内容审核AI，请严格审核以下内容：

【审核内容】
{content}

【审核规则】
1. 政治敏感：涉及国家领导人、政治事件、敏感历史
2. 违法违规：暴力、色情、赌博、毒品、诈骗
3. 人身攻击：侮辱、诽谤、歧视、仇恨言论
4. 广告营销：联系方式、引流信息、商业推广
5. 隐私泄露：身份证号、手机号、住址、银行卡号
6. 虚假信息：谣言、不实信息、误导性内容
7. 不良价值观：拜金主义、极端言论

【审核要求】
- 安全等级：{strict_level}级（1级最宽松，3级最严格）
- 必须输出纯JSON格式，不要任何额外文本

输出格式：
{{
    "passed": true/false,
    "score": 0-1之间的风险评分,
    "reasons": ["审核不通过的原因列表"],
    "suggestions": ["修改建议"],
    "flagged_keywords": ["违规关键词"],
    "risk_level": "low/medium/high",
    "sanitized_content": "净化后的内容（敏感词替换为*）"
}}"""

    def _quick_prefilter(self, content: str) -> tuple[bool, list]:
        """快速本地预过滤"""
        quick_blacklist = [
            # 联系方式
            "微信", "qq", "手机号", "电话", "加我", "v信", "vx",
            # 明显广告
            "加好友", "添加", "私聊", "代理", "招商",
            # 极端言论
            "死去", "杀死", "砍死", "操你",
            # 政治敏感
            "习近平", "毛泽东", "邓小平", "江泽民", "胡锦涛",
            # 色情低俗
            "做爱", "性交", "裸体", "色情"
        ]
        
        content_lower = content.lower()
        found_words = []
        
        for word in quick_blacklist:
            if word in content_lower:
                found_words.append(word)
        
        return len(found_words) == 0, found_words

    def audit_content(self, content: str, strict_level: int = 2) -> dict:
        """审核内容"""
        # 检查缓存
        cache_key = self._get_cache_key(content, strict_level)
        if cache_key in self.cache and self._is_cache_valid(self.cache[cache_key]):
            return self.cache[cache_key]['result']
        
        # 快速预过滤
        passed_prefilter, flagged_words = self._quick_prefilter(content)
        if not passed_prefilter:
            result = {
                "passed": False,
                "score": 0.9,
                "reasons": ["内容包含明显违规词汇"],
                "suggestions": ["请修改违规内容后重新提交"],
                "flagged_keywords": flagged_words,
                "risk_level": "high",
                "sanitized_content": self._sanitize_content(content, flagged_words)
            }
            # 缓存结果
            self.cache[cache_key] = {
                'result': result,
                'timestamp': time.time()
            }
            return result
        
        # DeepSeek API审核
        if not self.client:
            # 如果没有API密钥，使用基础规则审核
            return self._basic_audit(content, strict_level)
        
        try:
            prompt = self._get_audit_prompt(content, strict_level)
            
            response = self.client.chat.completions.create(
                model="deepseek-chat",
                messages=[
                    {"role": "system", "content": "你是一个严格的内容审核助手，必须输出纯JSON格式，不要任何额外文本。"},
                    {"role": "user", "content": prompt}
                ],
                temperature=0.1,
                max_tokens=1000
            )
            
            result_text = response.choices[0].message.content.strip()
            
            # 解析JSON
            try:
                result = json.loads(result_text)
            except json.JSONDecodeError:
                # 提取JSON部分
                import re
                json_match = re.search(r'\{.*\}', result_text, re.DOTALL)
                if json_match:
                    result = json.loads(json_match.group())
                else:
                    raise ValueError("无法解析JSON响应")
            
            # 缓存结果
            self.cache[cache_key] = {
                'result': result,
                'timestamp': time.time()
            }
            
            return result
            
        except Exception as e:
            print(f"DeepSeek审核异常: {e}")
            # 降级到基础审核
            return self._basic_audit(content, strict_level)
    
    def _basic_audit(self, content: str, strict_level: int) -> dict:
        """基础审核（当API不可用时）"""
        # 基础敏感词库
        basic_sensitive_words = [
            "敏感词", "违规内容", "不当言论", "政治", "暴力", 
            "色情", "赌博", "毒品", "诈骗", "人身攻击"
        ]
        
        content_lower = content.lower()
        found_words = []
        
        for word in basic_sensitive_words:
            if word in content_lower:
                found_words.append(word)
        
        if found_words:
            return {
                "passed": False,
                "score": 0.7 + (strict_level - 1) * 0.1,
                "reasons": [f"内容包含敏感词: {', '.join(found_words)}"],
                "suggestions": ["请修改敏感词汇后重新提交"],
                "flagged_keywords": found_words,
                "risk_level": "medium" if len(found_words) <= 2 else "high",
                "sanitized_content": self._sanitize_content(content, found_words)
            }
        else:
            return {
                "passed": True,
                "score": 0.1,
                "reasons": [],
                "suggestions": [],
                "flagged_keywords": [],
                "risk_level": "low",
                "sanitized_content": content
            }
    
    def _sanitize_content(self, content: str, flagged_words: list) -> str:
        """净化内容，将敏感词替换为*"""
        sanitized = content
        for word in flagged_words:
            sanitized = sanitized.replace(word, '*' * len(word))
        return sanitized
    
    def batch_audit(self, contents: List[str], strict_level: int = 2) -> List[dict]:
        """批量审核"""
        results = []
        for content in contents:
            result = self.audit_content(content, strict_level)
            results.append(result)
        return results

# 全局审核实例
audit_service = None

def init_audit_service(api_key: str = None):
    """初始化审核服务"""
    global audit_service
    audit_service = DeepSeekAudit(api_key)
    return audit_service

def get_audit_service() -> DeepSeekAudit:
    """获取审核服务实例"""
    global audit_service
    if audit_service is None:
        audit_service = DeepSeekAudit()
    return audit_service