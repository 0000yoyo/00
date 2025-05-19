# -*- coding: utf-8 -*-
"""
Created on Thu May 15 16:06:05 2025

@author: Henry
"""

# grammar_nlp_service.py
import spacy
import torch
from transformers import AutoTokenizer, AutoModelForSeq2SeqLM
from flask import Flask, request, jsonify

app = Flask(__name__)

# 載入模型 (可以選擇適合語法糾正的預訓練模型)
model_name = "facebook/bart-large-cnn"  # 可替換為GEC特定的模型
tokenizer = AutoTokenizer.from_pretrained(model_name)
model = AutoModelForSeq2SeqLM.from_pretrained(model_name)

# 載入spaCy用於語法分析
nlp = spacy.load("en_core_web_lg")

@app.route('/analyze', methods=['POST'])
def analyze_grammar():
    data = request.json
    text = data.get('text', '')
    
    # 使用spaCy進行基本語法分析
    doc = nlp(text)
    
    # 使用Transformer模型進行語法糾正
    inputs = tokenizer(text, return_tensors="pt", max_length=512, truncation=True)
    outputs = model.generate(inputs.input_ids, max_length=512)
    corrected_text = tokenizer.decode(outputs[0], skip_special_tokens=True)
    
    # 分析找出的錯誤
    grammar_issues = find_grammar_issues(text, corrected_text, doc)
    
    return jsonify({
        'corrected_text': corrected_text,
        'grammar_issues': grammar_issues
    })

@app.route('/finetune', methods=['POST'])
def finetune_model():
    data = request.json
    training_examples = data.get('examples', [])
    
    # 實現模型微調邏輯
    # ...
    
    return jsonify({'status': 'success', 'message': 'Model fine-tuning initiated'})

def find_grammar_issues(original, corrected, doc):
    # 這裡實現更複雜的錯誤檢測和分類邏輯
    issues = {
        "spelling": [],
        "grammar": [],
        "punctuation": [],
        "word_choice": [],
        "structure": [],
        "article": [],
        "tense": [],
        "subject_verb_agreement": [],
        "preposition": [],
        "plurals": []
    }
    
    # 使用差異檢測算法識別變化
    # ...
    
    return issues

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)