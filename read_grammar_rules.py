# -*- coding: utf-8 -*-
"""
Created on Wed May  7 21:35:50 2025

@author: Henry
"""

# 簡化版的read_grammar_rules.py
import pickle
import json

try:
    # 設定固定的路徑
    input_file = 'D:/xampp/htdocs/Project/models/grammar_rules.pkl'
    output_file = 'D:/xampp/htdocs/Project/temp/grammar_rules.json'
    
    # 嘗試讀取pickle文件
    with open(input_file, 'rb') as f:
        data = pickle.load(f)
    
    # 將數據轉換為JSON並保存
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    
    print(f"成功讀取 {input_file} 並轉換為 JSON")
except Exception as e:
    print(f"錯誤: {e}")