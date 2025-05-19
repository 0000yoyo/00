# -*- coding: utf-8 -*-
"""
Created on Wed May  7 21:36:34 2025

@author: Henry
"""

# 簡化版的save_grammar_rules.py
import pickle
import json

try:
    # 設定固定的路徑
    input_file = 'D:/xampp/htdocs/Project/temp/grammar_rules.json'
    output_file = 'D:/xampp/htdocs/Project/models/grammar_rules.pkl'
    
    # 讀取JSON文件
    with open(input_file, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    # 保存為pickle文件
    with open(output_file, 'wb') as f:
        pickle.dump(data, f)
    
    print(f"成功將JSON轉換並保存為 {output_file}")
except Exception as e:
    print(f"錯誤: {e}")