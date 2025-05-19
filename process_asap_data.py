# -*- coding: utf-8 -*-
"""
改進版資料集處理腳本 - 從 dev.src 和 dev.ref* 檔案提取文法規則
"""

import os
import pickle
import re
import sys
import numpy as np
from collections import defaultdict

def load_dev_files(file_names):
    """載入 dev.src 和 dev.ref* 檔案"""
    data = {
        'src': [],
        'refs': []
    }
    
    # 嘗試的編碼清單
    encodings = ['utf-8', 'latin-1', 'windows-1252', 'cp950']
    
    # 取得項目根目錄路徑
    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    data_dir = os.path.join(project_root, 'data', 'essays')
    
    print(f"項目根目錄: {project_root}")
    print(f"資料目錄: {data_dir}")
    
    # 確保資料目錄存在
    if not os.path.exists(data_dir):
        print(f"警告: 資料目錄不存在: {data_dir}")
        data_dir = os.getcwd()
        print(f"使用當前目錄作為資料目錄: {data_dir}")
    
    # 讀取 dev.src 檔案
    for file_name in file_names:
        if file_name == 'dev.src':
            src_path = os.path.join(data_dir, file_name)
            if os.path.exists(src_path):
                for encoding in encodings:
                    try:
                        with open(src_path, 'r', encoding=encoding) as f:
                            data['src'] = f.readlines()
                        print(f"成功使用 {encoding} 編碼載入 {src_path}，共 {len(data['src'])} 行")
                        break
                    except UnicodeDecodeError:
                        print(f"使用 {encoding} 編碼載入失敗")
                    except Exception as e:
                        print(f"載入 {src_path} 時發生錯誤: {str(e)}")
        elif file_name.startswith('dev.ref'):
            ref_path = os.path.join(data_dir, file_name)
            if os.path.exists(ref_path):
                for encoding in encodings:
                    try:
                        with open(ref_path, 'r', encoding=encoding) as f:
                            refs = f.readlines()
                        if refs:
                            data['refs'].append(refs)
                        print(f"成功使用 {encoding} 編碼載入 {ref_path}，共 {len(refs)} 行")
                        break
                    except UnicodeDecodeError:
                        print(f"使用 {encoding} 編碼載入失敗")
                    except Exception as e:
                        print(f"載入 {ref_path} 時發生錯誤: {str(e)}")
    
    return data

def extract_error_patterns(src_sentences, ref_sentences_list, verbose=True):
    """分析原始句子和校正句子的差異，提取錯誤模式"""
    if verbose:
        print(f"開始分析錯誤模式，源句子: {len(src_sentences)}，參考句子集: {len(ref_sentences_list)}")
    
    error_patterns = defaultdict(list)
    
    # 如果沒有足夠的資料，返回空結果
    if not src_sentences or not ref_sentences_list:
        print("警告: 沒有足夠的資料進行分析")
        return error_patterns
    
    for i, src in enumerate(src_sentences):
        src = src.strip()
        if not src:
            continue
        
        # 對於每個源句子，檢查對應的所有參考句子
        for ref_set in ref_sentences_list:
            if i < len(ref_set):
                ref = ref_set[i].strip()
                if ref and src != ref:  # 只處理不同的句子
                    # 提取差異
                    differences = identify_differences(src, ref)
                    
                    # 對每種錯誤類型記錄差異
                    for error_type, errors in differences.items():
                        for original, corrected in errors:
                            # 計算信心分數 - 基於多個參考集的一致性
                            confidence = calculate_confidence(src, original, corrected, ref_sentences_list, i)
                            
                            # 記錄錯誤模式
                            error_patterns[error_type].append({
                                'original': original,
                                'corrected': [corrected],
                                'confidence': confidence,
                                'context': extract_context(src, original),
                                'count': 1,
                                'examples': [src]
                            })
    
    # 合併相同的錯誤模式
    merged_patterns = defaultdict(list)
    for error_type, patterns in error_patterns.items():
        merged = {}
        for pattern in patterns:
            key = pattern['original']
            if key in merged:
                # 更新已存在的模式
                merged[key]['count'] += 1
                if pattern['corrected'][0] not in merged[key]['corrected']:
                    merged[key]['corrected'].append(pattern['corrected'][0])
                if pattern['examples'][0] not in merged[key]['examples']:
                    merged[key]['examples'].append(pattern['examples'][0])
                # 取較高的信心分數
                merged[key]['confidence'] = max(merged[key]['confidence'], pattern['confidence'])
            else:
                # 添加新模式
                merged[key] = pattern
        
        # 將合併後的模式轉換為列表
        merged_patterns[error_type] = list(merged.values())
        
        # 按出現頻率排序
        merged_patterns[error_type].sort(key=lambda x: x['count'], reverse=True)
    
    if verbose:
        for error_type, patterns in merged_patterns.items():
            print(f"找到 {error_type} 類型的錯誤: {len(patterns)} 個")
    
    return merged_patterns

def identify_differences(src, ref):
    """識別原始句子和校正句子之間的差異"""
    differences = {
        'spelling': [],
        'grammar': [],
        'punctuation': [],
        'word_choice': [],
        'tense': [],
        'article': [],
        'subject_verb_agreement': [],
        'preposition': [],
        'plurals': []
    }
    
    # 拆分為單詞
    src_words = re.findall(r'\b\w+\b|\W+', src.lower())
    ref_words = re.findall(r'\b\w+\b|\W+', ref.lower())
    
    # 使用最長公共子序列算法找出差異
    diff_regions = find_diff_regions(src_words, ref_words)
    
    for src_region, ref_region in diff_regions:
        # 組合區域中的單詞
        src_text = ''.join(src_words[src_region[0]:src_region[1]]).strip()
        ref_text = ''.join(ref_words[ref_region[0]:ref_region[1]]).strip()
        
        if not src_text or not ref_text:
            continue
        
        # 分類錯誤類型
        error_type = classify_error(src_text, ref_text)
        differences[error_type].append((src_text, ref_text))
    
    return differences

def find_diff_regions(src_words, ref_words):
    """使用最長公共子序列算法找出差異區域"""
    from difflib import SequenceMatcher
    matcher = SequenceMatcher(None, src_words, ref_words)
    diff_regions = []
    
    for opcode in matcher.get_opcodes():
        tag, i1, i2, j1, j2 = opcode
        if tag != 'equal':
            diff_regions.append(((i1, i2), (j1, j2)))
    
    return diff_regions

def classify_error(original, corrected):
    """分類錯誤類型，增強英文文法錯誤識別"""
    # 時態錯誤
    tense_indicators = [
        (r'\b(go|went|gone)\b', r'\b(go|went|gone)\b'),
        (r'\b(is|am|are|was|were)\b', r'\b(is|am|are|was|were)\b'),
        (r'\b(has|have|had)\b', r'\b(has|have|had)\b'),
        (r'\b(\w+)ing\b', r'\b(\w+)ing\b'),
        (r'\b(\w+)ed\b', r'\b(\w+)ed\b')
    ]
    
    for src_pattern, ref_pattern in tense_indicators:
        if (re.search(src_pattern, original) and re.search(ref_pattern, corrected)):
            return 'tense'
    
    # 主謂一致性問題
    sv_patterns = [
        (r'\b(I|we|you|they)\s+(is|was|has)\b', 'subject_verb_agreement'),
        (r'\b(he|she|it)\s+(are|were|have)\b', 'subject_verb_agreement'),
        (r'\b(\w+s)\s+(am|are|were)\b', 'subject_verb_agreement'),
        (r'\b(\w+)\s+(doesn\'t|don\'t)\b', 'subject_verb_agreement')
    ]
    
    for pattern, error_type in sv_patterns:
        if re.search(pattern, original, re.IGNORECASE):
            return error_type
    
    # 冠詞問題
    article_patterns = [
        (r'\ba\s+([aeiou]\w+)\b', 'article'),
        (r'\ban\s+([^aeiou\W]\w+)\b', 'article'),
        (r'\bthe\s+(\w+)\b', 'article')
    ]
    
    for pattern, error_type in article_patterns:
        if re.search(pattern, original, re.IGNORECASE) or re.search(pattern, corrected, re.IGNORECASE):
            return error_type
    
    # 複數形式問題
    plural_indicators = [
        (r'\b(\w+)s\b', r'\b\1\b'),
        (r'\b(\w+)\b', r'\b\1s\b')
    ]
    
    for src_pattern, ref_pattern in plural_indicators:
        src_matches = re.findall(src_pattern, original)
        ref_matches = re.findall(ref_pattern, corrected)
        if src_matches and ref_matches:
            return 'plurals'
    
    # 拼寫錯誤 - 如果長度相近但有差異
    if len(original) > 3 and len(corrected) > 3:
        from difflib import SequenceMatcher
        similarity = SequenceMatcher(None, original, corrected).ratio()
        if 0.7 < similarity < 0.95:
            return 'spelling'
    
    # 標點符號
    if re.search(r'[,.!?;:]', original) or re.search(r'[,.!?;:]', corrected):
        return 'punctuation'
    
    # 介詞錯誤
    prepositions = ['in', 'on', 'at', 'for', 'with', 'by', 'to', 'from', 'of', 'about']
    for prep in prepositions:
        if f" {prep} " in original or f" {prep} " in corrected:
            return 'preposition'
    
    # 默認為詞語選擇問題
    return 'word_choice'

def calculate_confidence(src, original, corrected, ref_sentences_list, index):
    """計算規則的信心分數 - 基於多個參考集的一致性"""
    agreement_count = 0
    total_refs = 0
    
    for ref_set in ref_sentences_list:
        if index < len(ref_set):
            ref = ref_set[index].strip()
            if ref:
                total_refs += 1
                # 檢查這個參考是否也做了相同的更正
                if original in src and corrected in ref:
                    agreement_count += 1
    
    # 如果只有一個參考集或沒有參考，給予中等信心
    if total_refs <= 1:
        return 0.7
    
    # 計算一致性比例
    agreement_ratio = agreement_count / total_refs
    
    # 轉換為信心分數，最低0.6，最高0.95
    confidence = 0.6 + (agreement_ratio * 0.35)
    
    return confidence

def extract_context(text, phrase):
    """提取短語周圍的上下文"""
    # 找到短語在文本中的位置
    phrase_pos = text.lower().find(phrase.lower())
    if phrase_pos == -1:
        return {"left": [], "right": []}
    
    # 提取左側上下文（最多3個單詞）
    left_text = text[:phrase_pos].strip()
    left_words = re.findall(r'\b\w+\b', left_text)
    left_context = left_words[-3:] if len(left_words) > 3 else left_words
    
    # 提取右側上下文（最多3個單詞）
    right_text = text[phrase_pos + len(phrase):].strip()
    right_words = re.findall(r'\b\w+\b', right_text)
    right_context = right_words[:3] if len(right_words) > 3 else right_words
    
    # 轉換為正則表達式模式
    left_patterns = []
    if left_context:
        left_patterns = [r'\b' + re.escape(' '.join(left_context[-i:])) + r'\b' for i in range(1, len(left_context) + 1)]
    
    right_patterns = []
    if right_context:
        right_patterns = [r'\b' + re.escape(' '.join(right_context[:i])) + r'\b' for i in range(1, len(right_context) + 1)]
    
    return {
        "left": left_patterns,
        "right": right_patterns
    }

def save_grammar_rules(rules, output_path, descriptions):
    """保存文法規則到檔案"""
    # 創建包含規則和描述的結構
    grammar_rules = {
        "rules": rules,
        "descriptions": descriptions
    }
    
    try:
        # 確保輸出目錄存在
        os.makedirs(os.path.dirname(output_path), exist_ok=True)
        
        # 保存規則
        with open(output_path, 'wb') as f:
            pickle.dump(grammar_rules, f)
        
        print(f"文法規則已保存至 {output_path}")
        return True
    except Exception as e:
        print(f"保存文法規則失敗: {e}")
        return False

def generate_basic_rules():
    """生成基本的文法規則"""
    return {
        "tense": [
            {
                "original": "buyed",
                "corrected": ["bought"],
                "confidence": 0.95,
                "count": 5,
                "examples": ["Yesterday I buyed a new book."]
            },
            {
                "original": "I plan to ate",
                "corrected": ["I plan to eat"],
                "confidence": 0.9,
                "count": 3,
                "examples": ["I plan to ate dinner later."]
            }
        ],
        "subject_verb_agreement": [
            {
                "original": "I has",
                "corrected": ["I have"],
                "confidence": 0.95,
                "count": 8,
                "examples": ["I has finished my homework."]
            },
            {
                "original": "She write",
                "corrected": ["She writes"],
                "confidence": 0.95,
                "count": 6,
                "examples": ["She write every day."]
            },
            {
                "original": "This essay express",
                "corrected": ["This essay expresses"],
                "confidence": 0.9,
                "count": 4,
                "examples": ["This essay express my feelings."]
            }
        ],
        "plurals": [
            {
                "original": "childrens",
                "corrected": ["children"],
                "confidence": 0.95,
                "count": 7,
                "examples": ["The childrens are playing."]
            }
        ],
        "grammar": [
            {
                "original": "My brother and me went",
                "corrected": ["My brother and I went"],
                "confidence": 0.9,
                "count": 5,
                "examples": ["My brother and me went to the park."]
            }
        ]
    }

def main():
    """主函數：處理資料集並提取文法規則"""
    print("=== 開始處理資料集並提取文法規則 ===")
    
    # 定義資料檔案
    files = ['dev.src', 'dev.ref0', 'dev.ref1', 'dev.ref2', 'dev.ref3']
    
    # 載入資料
    print("\n載入資料中...")
    data = load_dev_files(files)
    
    # 檢查是否成功載入資料
    use_loaded_data = True
    if not data['src'] or not data['refs']:
        print("\n警告: 資料載入不完整")
        use_loaded_data = False
    
    # 提取錯誤模式
    error_patterns = {}
    if use_loaded_data:
        print("\n分析錯誤模式中...")
        error_patterns = extract_error_patterns(data['src'], data['refs'])
    
    # 如果沒有足夠的規則，使用基本規則
    if not error_patterns or sum(len(patterns) for patterns in error_patterns.values()) < 10:
        print("\n提取的規則不足，使用基本規則...")
        error_patterns = generate_basic_rules()
    
    # 錯誤類型的中文描述
    descriptions = {
        "spelling": "拼寫錯誤",
        "grammar": "文法錯誤",
        "punctuation": "標點符號錯誤",
        "word_choice": "詞語選擇不當",
        "structure": "句子結構問題",
        "article": "冠詞使用錯誤",
        "tense": "時態使用錯誤",
        "subject_verb_agreement": "主謂一致性問題",
        "preposition": "介詞使用錯誤",
        "plurals": "複數形式錯誤",
        "unknown": "其他錯誤"
    }
    
    # 輸出分析結果
    print("\n分析結果:")
    total_rules = 0
    for error_type, patterns in error_patterns.items():
        rule_count = len(patterns)
        total_rules += rule_count
        print(f"\n{descriptions.get(error_type, error_type)} (共 {rule_count} 種模式):")
        for i, rule in enumerate(patterns[:5]):  # 顯示前5個最常見的規則
            corrected_str = rule['corrected'][0] if rule['corrected'] else ""
            print(f"  {i+1}. '{rule['original']}' -> '{corrected_str}' (信心: {rule['confidence']:.2f})")
            if rule['examples']:
                print(f"     例句: \"{rule['examples'][0].strip()}\"")
    
    print(f"\n總共提取了 {total_rules} 條規則")
    
    # 保存分析結果
    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    output_path = os.path.join(project_root, "models", "grammar_rules.pkl")
    
    if save_grammar_rules(error_patterns, output_path, descriptions):
        print("\n文法規則已成功保存")
    else:
        print("\n保存文法規則失敗")
    
    print("\n=== 資料集處理完成 ===")

if __name__ == "__main__":
    main()