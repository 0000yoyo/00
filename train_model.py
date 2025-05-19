# -*- coding: utf-8 -*-
"""
Created on Mon May  5 22:43:56 2025

@author: Henry
"""

#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
模型訓練腳本 - 使用 ASAP 資料集訓練作文評分模型
"""

import pandas as pd
import numpy as np
import os
import pickle
import re
import sys
import datetime
import json
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_squared_error, cohen_kappa_score
from sklearn.pipeline import Pipeline
from sklearn.feature_extraction.text import TfidfVectorizer
import argparse

# 設置隨機種子確保結果可重現
np.random.seed(42)

def clean_text(text):
    """基本文本清理函數"""
    # 檢查是否為字符串
    if not isinstance(text, str):
        return ""
    
    # 轉換為小寫
    text = text.lower()
    # 移除特殊字符但保留標點符號
    text = re.sub(r'[^\w\s\.\,\?\!]', '', text)
    # 將多個空格替換為單個空格
    text = re.sub(r'\s+', ' ', text)
    return text.strip()

def extract_features(text):
    """從文本中提取特徵"""
    features = {}
    
    # 基本計數特徵
    features['char_count'] = len(text)
    features['word_count'] = len(text.split())
    features['sentence_count'] = text.count('.') + text.count('!') + text.count('?')
    
    # 平均長度特徵
    if features['sentence_count'] > 0:
        features['avg_sentence_length'] = features['word_count'] / features['sentence_count']
    else:
        features['avg_sentence_length'] = features['word_count']
    
    if features['word_count'] > 0:
        features['avg_word_length'] = features['char_count'] / features['word_count']
    else:
        features['avg_word_length'] = 0
    
    # 詞彙豐富度 (不重複詞數 / 總詞數)
    unique_words = set(text.split())
    features['lexical_diversity'] = len(unique_words) / features['word_count'] if features['word_count'] > 0 else 0
    
    # 標點符號使用頻率
    features['comma_count'] = text.count(',')
    features['question_count'] = text.count('?')
    features['exclamation_count'] = text.count('!')
    
    # 各類標點符號比例
    if features['sentence_count'] > 0:
        features['question_ratio'] = features['question_count'] / features['sentence_count']
        features['exclamation_ratio'] = features['exclamation_count'] / features['sentence_count']
    else:
        features['question_ratio'] = 0
        features['exclamation_ratio'] = 0
    
    return features

def load_asap_data(file_path):
    """載入 ASAP 資料集"""
    try:
        # 修改為檢查相對於項目根目錄的路徑
        full_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'data', 'essays', file_path)
        
        if not os.path.exists(full_path):
            print(f"警告：找不到文件 {full_path}")
            # 嘗試直接從當前目錄讀取
            full_path = file_path
            
        # 添加編碼參數
        df = pd.read_csv(full_path, sep='\t', encoding='latin-1')  # 嘗試 latin-1 編碼
        print(f"成功載入資料集，共 {len(df)} 筆資料")
        
        # 檢查關鍵欄位是否存在
        required_columns = ['essay_id', 'essay_set', 'essay', 'domain1_score']
        for col in required_columns:
            if col not in df.columns:
                print(f"警告：資料集缺少必要欄位 '{col}'")
        
        return df
    except Exception as e:
        print(f"載入資料集失敗: {e}")
        # 如果 latin-1 編碼失敗，嘗試其他編碼
        try:
            df = pd.read_csv(full_path, sep='\t', encoding='windows-1252')
            print(f"成功使用 windows-1252 編碼載入資料集，共 {len(df)} 筆資料")
            return df
        except Exception as e2:
            print(f"使用 windows-1252 編碼載入失敗: {e2}")
            return pd.DataFrame()

def train_model(df, essay_set=1, model_version='1.0.0', feedback_data=None):
    """訓練模型，可選擇性地包含教師反饋數據"""
    if len(df) == 0:
        print("錯誤：資料集為空，無法訓練模型")
        return None, None, 0, 0
    
    # 僅使用指定的 essay_set（如果有指定）
    if 'essay_set' in df.columns:
        df = df[df['essay_set'] == essay_set].copy()
    
    if len(df) == 0:
        print(f"錯誤：資料集中沒有 essay_set={essay_set} 的數據")
        return None, None, 0, 0
    
    print(f"使用 essay_set={essay_set} 的數據進行訓練，共 {len(df)} 筆")
    
    # 清理文本
    df['cleaned_essay'] = df['essay'].apply(clean_text)
    
    # 提取目標變數
    if 'domain1_score' in df.columns:
        y = df['domain1_score'].values
    else:
        print("錯誤：資料集缺少目標欄位 'domain1_score'")
        return None, None, 0, 0
    
    # 提取特徵
    features_list = []
    for text in df['cleaned_essay']:
        features_list.append(extract_features(text))
    
    features_df = pd.DataFrame(features_list)
    
    # 新增：如果有教師反饋數據，整合到訓練中
    if feedback_data is not None and len(feedback_data) > 0:
        print(f"整合 {len(feedback_data)} 條教師反饋到訓練中...")
        
        # 創建額外的訓練樣本
        extra_features = []
        extra_y = []
        
        for feedback in feedback_data:
            essay_id = feedback['essay_id']
            content = feedback.get('content', '')
            ai_score = feedback.get('ai_score', 0)
            teacher_score = feedback.get('teacher_score', 0)
            
            if content and teacher_score:
                # 提取特徵
                cleaned_text = clean_text(content)
                features = extract_features(cleaned_text)
                
                # 添加到額外的訓練數據
                extra_features.append(features)
                extra_y.append(teacher_score)
                
                print(f"添加反饋樣本 essay_id={essay_id}: AI={ai_score}, 教師={teacher_score}")
        
        if extra_features:
            # 合併原始特徵和額外特徵
            extra_df = pd.DataFrame(extra_features)
            features_df = pd.concat([features_df, extra_df], ignore_index=True)
            
            # 合併原始分數和額外分數
            y = np.concatenate([y, np.array(extra_y)])
            
            print(f"成功添加 {len(extra_features)} 筆反饋數據到訓練集")
    
    # 分割資料集
    X_train, X_test, y_train, y_test = train_test_split(
        features_df, y, test_size=0.2, random_state=42
    )
    
    # 訓練模型
    print("開始訓練模型...")
    model = RandomForestRegressor(
        n_estimators=100,
        max_depth=10,
        random_state=42
    )
    
    # 訓練
    model.fit(X_train, y_train)
    
    # 評估模型
    y_pred = model.predict(X_test)
    rmse = np.sqrt(mean_squared_error(y_test, y_pred))
    
    # 四捨五入到整數
    y_pred_rounded = np.round(y_pred).astype(int)
    y_test_rounded = np.round(y_test).astype(int)
    
    # 計算 Cohen's Kappa
    kappa = cohen_kappa_score(y_test_rounded, y_pred_rounded, weights='quadratic')
    
    print(f"模型評估結果:")
    print(f"RMSE: {rmse:.4f}")
    print(f"Quadratic Weighted Kappa: {kappa:.4f}")
    
    # 特徵重要性
    feature_importance = pd.DataFrame({
        'feature': features_df.columns,
        'importance': model.feature_importances_
    }).sort_values('importance', ascending=False)
    
    print("\n特徵重要性:")
    for index, row in feature_importance.head(5).iterrows():
        print(f"{row['feature']}: {row['importance']:.4f}")
    
    return model, features_df.columns.tolist(), rmse, kappa

def save_model(model, feature_names, rmse, kappa, essay_set, version="1.0.0"):
    """保存模型和特徵列名"""
    # 創建模型目錄
    if not os.path.exists('models'):
        os.makedirs('models')
    
    # 保存模型
    model_path = f'models/essay_scoring_model_v{version}.pkl'
    with open(model_path, 'wb') as f:
        pickle.dump((model, feature_names), f)
    
    print(f"模型已保存到 {model_path}")
    
    # 創建訓練記錄
    training_info = {
        'model_version': version,
        'training_date': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'essay_set': essay_set,
        'rmse': rmse,
        'kappa': kappa,
        'feature_count': len(feature_names)
    }
    
    # 保存訓練記錄
    record_path = f'models/training_record_v{version}.json'
    with open(record_path, 'w', encoding='utf-8') as f:
        json.dump(training_info, f, ensure_ascii=False, indent=2)
    
    print(f"訓練記錄已保存到 {record_path}")
    
    return training_info

def main():
    """主函數"""
    parser = argparse.ArgumentParser(description='訓練作文評分模型')
    parser.add_argument('--data', default='training_set_rel3.tsv', help='資料集文件路徑')
    parser.add_argument('--feedback', help='教師反饋數據JSON文件路徑')
    parser.add_argument('--essay_set', type=int, default=1, help='使用哪個 essay_set 進行訓練')
    parser.add_argument('--version', default='1.0.0', help='模型版本')
    
    args = parser.parse_args()
    
    print("開始訓練作文評分模型...")
    
    # 載入資料
    df = load_asap_data(args.data)
    
    if len(df) == 0:
        print("無法載入資料集，訓練終止")
        return
    
    # 加載教師反饋數據
    feedback_data = None
    if args.feedback:
        try:
            with open(args.feedback, 'r', encoding='utf-8') as f:
                feedback_data = json.load(f)
                print(f"成功載入 {len(feedback_data)} 條教師反饋數據")
        except Exception as e:
            print(f"載入反饋數據失敗: {e}")
    
    # 訓練模型
    model, feature_names, rmse, kappa = train_model(df, args.essay_set, args.version, feedback_data)
    
    if model is None:
        print("模型訓練失敗")
        return
    
    # 保存模型
    training_info = save_model(model, feature_names, rmse, kappa, args.essay_set, args.version)
    
    # 創建資料庫記錄所需的 SQL
    feedback_count = len(feedback_data) if feedback_data else 0
    sql = f"""
    INSERT INTO model_training 
    (model_version, training_date, dataset_size, accuracy, notes) 
    VALUES 
    ('{training_info['model_version']}', 
     '{training_info['training_date']}', 
     {len(df)}, 
     {training_info['kappa']}, 
     'Trained on essay_set {training_info['essay_set']} with {feedback_count} teacher feedback items')
    """
    
    print("\n資料庫記錄 SQL:")
    print(sql)
    
    print("\n訓練完成！")

if __name__ == "__main__":
    main()