#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
文法檢測測試腳本
"""

import sys
import os
import re
import pickle
import json
import argparse

def load_grammar_rules(file_path=None):
    """載入儲存的文法規則"""
    # 預設路徑
    default_paths = [
        'D:/xampp/htdocs/Project/models/grammar_rules.pkl',  # 使用您的絕對路徑
        os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'models', 'grammar_rules.pkl'),
        os.path.join('models', 'grammar_rules.pkl'),
        os.path.join(os.getcwd(), 'models', 'grammar_rules.pkl')
    ]
    
    # 如果指定了文件路徑，優先嘗試
    if file_path and os.path.exists(file_path):
        try:
            print(f"嘗試從指定路徑載入: {file_path}")
            with open(file_path, 'rb') as f:
                data = pickle.load(f)
                if isinstance(data, dict) and "rules" in data:
                    return data["rules"]
                return data.get("rules", {})
        except Exception as e:
            print(f"無法從指定路徑載入規則: {e}", file=sys.stderr)
    
    # 嘗試預設路徑
    for path in default_paths:
        if os.path.exists(path):
            try:
                print(f"嘗試從路徑載入: {path}")
                with open(path, 'rb') as f:
                    data = pickle.load(f)
                    if isinstance(data, dict) and "rules" in data:
                        return data["rules"]
                    return data.get("rules", {})
            except Exception as e:
                print(f"載入文件 {path} 失敗: {e}", file=sys.stderr)
    
    return {}

def check_grammar_issues(text, rules=None):
    """檢查文本中的文法問題，優化英文文法檢測"""
    issues = {}
    
    # 如果未提供規則，則載入預設規則
    if rules is None:
        rules = load_grammar_rules()
    
    # 如果沒有規則，則返回空結果
    if not rules:
        print("無法載入任何文法規則")
        return issues
    
    print(f"成功載入規則，類型數: {len(rules)}")
    for type_name, type_rules in rules.items():
        print(f"  - {type_name}: {len(type_rules)} 條規則")
    
    # 針對每種錯誤類型檢查
    for error_type, type_rules in rules.items():
        found_issues = []
        for rule in type_rules:
            # 確保規則格式正確
            if 'original' not in rule or 'corrected' not in rule or not rule['corrected']:
                continue
            
            original = rule['original']
            
            try:
                # 對於短語類型的錯誤，使用更寬鬆的匹配方式
                if ' ' in original:
                    # 允許短語中間有變化的字詞間距
                    pattern = r'(?i)\b' + re.escape(original).replace(r'\ ', r'\s+') + r'\b'
                else:
                    pattern = r'(?i)\b' + re.escape(original) + r'\b'
                
                matches = re.findall(pattern, text)
                
                if matches:
                    corrected = rule['corrected'][0] if rule['corrected'] else ""
                    suggestion = f"'{original}' 可能應為 '{corrected}'"
                    if suggestion not in found_issues:
                        found_issues.append(suggestion)
                        print(f"發現問題: {suggestion}")
            except Exception as e:
                print(f"匹配規則 '{original}' 時出錯: {e}", file=sys.stderr)
        
        if found_issues:
            issues[error_type] = found_issues
    
    # 如果沒有找到任何已知規則的問題，嘗試應用基本英文文法規則
    if not issues:
        print("未找到任何已知規則的問題，嘗試應用基本英文文法規則...")
        basic_issues = check_basic_english_grammar(text)
        if basic_issues:
            issues.update(basic_issues)
    
    return issues

def check_basic_english_grammar(text):
    """檢查基本英文文法問題"""
    issues = {}
    
    # 主謂一致性檢查
    sv_patterns = [
        (r'\b(I|we|you|they)\s+(is|was|has)\b', 'subject_verb_agreement'),
        (r'\b(he|she|it)\s+(are|were|have)\b', 'subject_verb_agreement'),
        (r'\b(a|an|the)\s+([a-z]+s)\b', 'article')  # 檢查冠詞後接複數名詞
    ]
    
    for pattern, error_type in sv_patterns:
        try:
            matches = re.findall(pattern, text, re.IGNORECASE)
            if matches:
                if error_type not in issues:
                    issues[error_type] = []
                
                for match in matches:
                    if isinstance(match, tuple):
                        subject, verb = match
                        if (subject.lower() in ['i', 'we', 'you', 'they'] and 
                            verb.lower() in ['is', 'was', 'has']):
                            correct_verb = {'is': 'are', 'was': 'were', 'has': 'have'}[verb.lower()]
                            suggestion = f"'{subject} {verb}' 可能應為 '{subject} {correct_verb}'"
                            issues[error_type].append(suggestion)
                            print(f"發現基本文法問題: {suggestion}")
                        elif (subject.lower() in ['he', 'she', 'it'] and 
                              verb.lower() in ['are', 'were', 'have']):
                            correct_verb = {'are': 'is', 'were': 'was', 'have': 'has'}[verb.lower()]
                            suggestion = f"'{subject} {verb}' 可能應為 '{subject} {correct_verb}'"
                            issues[error_type].append(suggestion)
                            print(f"發現基本文法問題: {suggestion}")
                    else:
                        suggestion = f"'{match}' 可能有文法問題"
                        issues[error_type].append(suggestion)
                        print(f"發現基本文法問題: {suggestion}")
        except Exception as e:
            print(f"檢查模式 '{pattern}' 時出錯: {e}", file=sys.stderr)
    
    return issues

def main():
    """主函數"""
    parser = argparse.ArgumentParser(description='檢查文本中的文法問題')
    parser.add_argument('-f', '--file', help='要檢查的文本文件路徑')
    parser.add_argument('-t', '--text', help='直接輸入要檢查的文本')
    parser.add_argument('-r', '--rules', help='文法規則文件路徑')
    args = parser.parse_args()
    
    # 從文件或參數獲取文本
    text = None
    if args.file:
        try:
            with open(args.file, 'r', encoding='utf-8') as f:
                text = f.read()
        except Exception as e:
            print(f"讀取文本文件失敗: {e}", file=sys.stderr)
            sys.exit(1)
    elif args.text:
        text = args.text
    else:
        print("請使用 -f/--file 指定文本文件或使用 -t/--text 直接輸入文本")
        parser.print_help()
        sys.exit(1)
    
    # 檢測文法問題
    issues = check_grammar_issues(text, load_grammar_rules(args.rules))
    
    # 輸出 JSON 結果
    result = {
        'success': True,
        'issues': issues
    }
    
    print("\n結果:")
    print(json.dumps(result, ensure_ascii=False, indent=2))

if __name__ == "__main__":
    main()