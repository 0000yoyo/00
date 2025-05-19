# -*- coding: utf-8 -*-
"""
Created on Thu May 15 16:10:06 2025

@author: Henry
"""

#!/usr/bin/env python
# -*- coding: utf-8 -*-

from flask import Flask, request, jsonify
import os
import json
import spacy
import torch
from transformers import AutoTokenizer, AutoModelForSeq2SeqLM
from utils.grammar_analyzer import GrammarAnalyzer
from utils.model_trainer import ModelTrainer

app = Flask(__name__)

# 初始化
model_dir = os.path.join(os.path.dirname(__file__), 'models')
training_data_dir = os.path.join(os.path.dirname(__file__), 'training_data')

os.makedirs(model_dir, exist_ok=True)
os.makedirs(training_data_dir, exist_ok=True)

# 載入模型 (優先載入本地微調模型，若不存在則載入預訓練模型)
MODEL_NAME = "gec-t5"  # 可替換為其他語法糾正模型
LOCAL_MODEL_PATH = os.path.join(model_dir, "grammar-model-latest")

if os.path.exists(LOCAL_MODEL_PATH):
    print(f"Loading model from {LOCAL_MODEL_PATH}")
    tokenizer = AutoTokenizer.from_pretrained(LOCAL_MODEL_PATH)
    model = AutoModelForSeq2SeqLM.from_pretrained(LOCAL_MODEL_PATH)
else:
    print(f"Loading pretrained model: {MODEL_NAME}")
    tokenizer = AutoTokenizer.from_pretrained(MODEL_NAME)
    model = AutoModelForSeq2SeqLM.from_pretrained(MODEL_NAME)

# 載入spaCy用於語法分析
try:
    nlp = spacy.load("en_core_web_lg")
except:
    # 如果模型不存在，嘗試下載
    os.system("python -m spacy download en_core_web_lg")
    nlp = spacy.load("en_core_web_lg")

# 初始化語法分析器
grammar_analyzer = GrammarAnalyzer(nlp, tokenizer, model)
model_trainer = ModelTrainer(model_dir, training_data_dir)

@app.route('/health', methods=['GET'])
def health_check():
    """健康檢查端點"""
    return jsonify({
        'status': 'healthy',
        'model': MODEL_NAME if not os.path.exists(LOCAL_MODEL_PATH) else 'custom-model',
        'version': '1.0.0'
    })

@app.route('/analyze', methods=['POST'])
def analyze_grammar():
    """文法分析端點"""
    data = request.json
    if not data or 'text' not in data:
        return jsonify({
            'error': 'Missing text parameter'
        }), 400
    
    text = data.get('text', '')
    
    # 進行語法分析
    try:
        results = grammar_analyzer.analyze(text)
        return jsonify(results)
    except Exception as e:
        print(f"Error during analysis: {str(e)}")
        return jsonify({
            'error': str(e)
        }), 500

@app.route('/finetune', methods=['POST'])
def finetune_model():
    """模型微調端點"""
    data = request.json
    if not data or 'examples' not in data:
        return jsonify({
            'error': 'Missing examples parameter'
        }), 400
    
    examples = data.get('examples', [])
    
    try:
        result = model_trainer.save_training_examples(examples)
        # 僅保存訓練數據，實際訓練由定期調度腳本執行
        return jsonify({
            'status': 'success',
            'message': 'Training examples saved successfully',
            'examples_count': len(examples),
            'training_job': result
        })
    except Exception as e:
        print(f"Error during finetune: {str(e)}")
        return jsonify({
            'error': str(e)
        }), 500

@app.route('/reload-model', methods=['POST'])
def reload_model():
    """重新載入最新模型"""
    global tokenizer, model
    
    try:
        latest_model_dir = model_trainer.get_latest_model_dir()
        if latest_model_dir and os.path.exists(latest_model_dir):
            tokenizer = AutoTokenizer.from_pretrained(latest_model_dir)
            model = AutoModelForSeq2SeqLM.from_pretrained(latest_model_dir)
            grammar_analyzer.update_model(tokenizer, model)
            
            return jsonify({
                'status': 'success',
                'message': f'Model reloaded from {latest_model_dir}'
            })
        else:
            return jsonify({
                'status': 'error',
                'message': 'No local model found'
            }), 404
    except Exception as e:
        return jsonify({
            'status': 'error',
            'message': str(e)
        }), 500

if __name__ == '__main__':
    # 開發環境使用
    app.run(host='0.0.0.0', port=5000, debug=True)