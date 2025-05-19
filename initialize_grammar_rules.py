# -*- coding: utf-8 -*-
"""
Created on Wed May  7 21:27:39 2025

@author: Henry
"""

import pickle

# 定義更結構化的文法規則格式
grammar_rules = {
    "rules": {
        "tense": [],           # 時態錯誤
        "subject_verb_agreement": [],  # 主謂一致
        "article": [],         # 冠詞錯誤
        "plurals": [],         # 複數形式
        "preposition": [],     # 介詞錯誤
        "word_choice": [],     # 詞語選擇
        "spelling": []         # 拼寫錯誤
    },
    "descriptions": {
        "tense": "時態使用錯誤",
        "subject_verb_agreement": "主謂一致性問題",
        "article": "冠詞使用錯誤",
        "plurals": "複數形式錯誤",
        "preposition": "介詞使用錯誤",
        "word_choice": "詞語選擇不當",
        "spelling": "拼寫錯誤"
    }
}