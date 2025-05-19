from flask import Flask, request, jsonify
import pickle
import numpy as np
import nltk
from nltk.tokenize import word_tokenize
import os
import json
import datetime
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.ensemble import RandomForestClassifier
import re

app = Flask(__name__)

# 下載必要的 NLTK 資源
nltk.download('punkt')

# 初始化模型
model_path = os.path.join(os.path.dirname(__file__), 'models')
if not os.path.exists(model_path):
    os.makedirs(model_path)

# 文法分析模型檔案路徑
grammar_model_path = os.path.join(model_path, 'grammar_model.pkl')
vectorizer_path = os.path.join(model_path, 'vectorizer.pkl')

# 訓練資料儲存路徑
training_data_path = os.path.join(os.path.dirname(__file__), 'data')
if not os.path.exists(training_data_path):
    os.makedirs(training_data_path)

# 載入模型 (如果存在)
def load_models():
    try:
        if os.path.exists(grammar_model_path) and os.path.exists(vectorizer_path):
            with open(grammar_model_path, 'rb') as f:
                model = pickle.load(f)
            with open(vectorizer_path, 'rb') as f:
                vectorizer = pickle.load(f)
            return model, vectorizer
        else:
            return None, None
    except Exception as e:
        print(f"載入模型時發生錯誤: {str(e)}")
        return None, None

# 預處理文本
def preprocess_text(text):
    # 轉小寫
    text = text.lower()
    # 分詞
    tokens = word_tokenize(text)
    # 去除標點符號和數字
    tokens = [token for token in tokens if token.isalpha()]
    # 合併回文本
    return " ".join(tokens)

# 提取特徵
def extract_features(text, vectorizer=None):
    preprocessed_text = preprocess_text(text)
    
    if vectorizer is None:
        vectorizer = TfidfVectorizer(max_features=5000)
        features = vectorizer.fit_transform([preprocessed_text])
        return features, vectorizer
    else:
        features = vectorizer.transform([preprocessed_text])
        return features

# 文法規則分析 API 端點
@app.route('/api/analyze_grammar', methods=['POST'])
def analyze_grammar():
    data = request.json
    if 'text' not in data:
        return jsonify({'error': 'No text provided'}), 400
    
    text = data['text']
    category = data.get('category', 'general')
    
    # 載入模型
    model, vectorizer = load_models()
    
    # 如果模型不存在，返回空結果
    if model is None or vectorizer is None:
        return jsonify({
            'grammar_issues': {},
            'score': 85,  # 預設分數
            'model_version': 'default'
        })
    
    # 提取特徵
    features = extract_features(text, vectorizer)
    
    # 預測文法問題
    try:
        # 簡單示範：預測文法問題類型
        # 實際情況下，可能需要更複雜的邏輯
        predictions = model.predict(features)
        prediction_proba = model.predict_proba(features)
        
        # 解析預測結果
        grammar_issues = {}
        for i, issue_type in enumerate(model.classes_):
            if prediction_proba[0][i] > 0.3:  # 機率閾值
                grammar_issues[issue_type] = detect_issues(text, issue_type)
        
        # 計算分數 (根據文法問題數量)
        issue_count = sum(len(issues) for issues in grammar_issues.values())
        score = max(60, 95 - (issue_count * 2))
        
        return jsonify({
            'grammar_issues': grammar_issues,
            'score': score,
            'model_version': '1.0'
        })
    except Exception as e:
        print(f"預測時發生錯誤: {str(e)}")
        return jsonify({
            'grammar_issues': {},
            'score': 85,
            'model_version': 'default',
            'error': str(e)
        })

# 根據問題類型檢測特定問題
def detect_issues(text, issue_type):
    issues = []
    
    # 這裡應該實現根據不同問題類型的檢測邏輯
    # 以下是一個簡單的示範
    if issue_type == 'subject_verb_agreement':
        # 檢查主謂一致性問題的簡單規則
        patterns = [
            (r'\bthey is\b', 'they are'),
            (r'\bhe are\b', 'he is'),
            (r'\bshe are\b', 'she is'),
            (r'\bit are\b', 'it is')
        ]
        for pattern, correction in patterns:
            if re.search(pattern, text.lower()):
                issues.append(f"'{pattern[2:-2]}' 應為 '{correction}'")
    
    elif issue_type == 'tense':
        # 檢查時態問題
        patterns = [
            (r'\bhave went\b', 'have gone'),
            (r'\bhas went\b', 'has gone'),
            (r'\bwill went\b', 'will go')
        ]
        for pattern, correction in patterns:
            if re.search(pattern, text.lower()):
                issues.append(f"'{pattern[2:-2]}' 應為 '{correction}'")
    
    # 可以添加更多問題類型的檢測邏輯
    
    return issues

# 接收教師反饋以進行訓練
@app.route('/api/submit_feedback', methods=['POST'])
def submit_feedback():
    data = request.json
    
    if 'essay_text' not in data or 'feedback' not in data:
        return jsonify({'error': 'Missing required fields'}), 400
    
    # 儲存反饋資料以供後續訓練
    timestamp = datetime.datetime.now().strftime("%Y%m%d%H%M%S")
    feedback_file = os.path.join(training_data_path, f'feedback_{timestamp}.json')
    
    with open(feedback_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    
    return jsonify({'success': True, 'message': 'Feedback received for training'})

# 訓練模型 API
@app.route('/api/train_model', methods=['POST'])
def train_model():
    # 獲取所有反饋資料
    feedback_files = [os.path.join(training_data_path, f) for f in os.listdir(training_data_path) 
                     if f.startswith('feedback_') and f.endswith('.json')]
    
    if not feedback_files:
        return jsonify({'success': False, 'message': 'No feedback data available for training'})
    
    # 載入反饋資料
    training_data = []
    for file in feedback_files:
        try:
            with open(file, 'r', encoding='utf-8') as f:
                feedback = json.load(f)
                training_data.append(feedback)
        except Exception as e:
            print(f"讀取反饋檔案時發生錯誤: {str(e)}")
    
    # 解析反饋數據進行訓練
    X = []  # 文本特徵
    y = []  # 問題類型標籤
    
    for feedback in training_data:
        essay_text = feedback.get('essay_text', '')
        
        for issue in feedback.get('feedback', []):
            issue_type = issue.get('type', '')
            wrong_expr = issue.get('wrong', '')
            
            if essay_text and issue_type and wrong_expr:
                # 從文章中提取包含錯誤表達的上下文
                context = extract_context(essay_text, wrong_expr)
                if context:
                    X.append(context)
                    y.append(issue_type)
    
    if not X or not y:
        return jsonify({'success': False, 'message': 'No valid training examples found'})
    
    # 訓練向量化器
    vectorizer = TfidfVectorizer(max_features=5000)
    X_features = vectorizer.fit_transform(X)
    
    # 訓練模型 (使用隨機森林分類器作為示範)
    model = RandomForestClassifier(n_estimators=100, random_state=42)
    model.fit(X_features, y)
    
    # 儲存模型
    with open(grammar_model_path, 'wb') as f:
        pickle.dump(model, f)
    
    with open(vectorizer_path, 'wb') as f:
        pickle.dump(vectorizer, f)
    
    # 儲存訓練資訊
    training_info = {
        'timestamp': datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        'num_examples': len(X),
        'classes': list(set(y)),
        'model_version': '1.0.1'
    }
    
    info_path = os.path.join(model_path, 'training_info.json')
    with open(info_path, 'w', encoding='utf-8') as f:
        json.dump(training_info, f, ensure_ascii=False, indent=2)
    
    return jsonify({
        'success': True, 
        'message': f'Model trained with {len(X)} examples',
        'training_info': training_info
    })

# 從文章中提取包含錯誤表達的上下文
def extract_context(text, expression, context_size=50):
    idx = text.lower().find(expression.lower())
    if idx >= 0:
        start = max(0, idx - context_size)
        end = min(len(text), idx + len(expression) + context_size)
        return text[start:end]
    return expression  # 如果找不到，直接返回表達式本身

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)