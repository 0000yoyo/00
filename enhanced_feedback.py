#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Grammar Analysis Module
More powerful grammar analysis tool for the Essay Correction Platform

This module provides enhanced grammar checking functionality by:
1. Loading and analyzing grammar rules from the pickle file
2. Checking text content for grammar issues using pattern matching
3. Providing detailed analysis results with suggested corrections
4. Supporting both English and Chinese grammar patterns
"""

import re
import sys
import os
import pickle
import json
from collections import defaultdict
import argparse

# Error type descriptions (both English and Chinese)
ERROR_DESCRIPTIONS = {
    "spelling": "拼寫錯誤 (Spelling Error)",
    "grammar": "文法錯誤 (Grammar Error)",
    "punctuation": "標點符號錯誤 (Punctuation Error)",
    "word_choice": "詞語選擇不當 (Word Choice Issue)",
    "structure": "句子結構問題 (Sentence Structure Issue)",
    "article": "冠詞使用錯誤 (Article Usage Error)",
    "tense": "時態使用錯誤 (Tense Error)",
    "subject_verb_agreement": "主謂一致性問題 (Subject-Verb Agreement Issue)",
    "preposition": "介詞使用錯誤 (Preposition Error)",
    "plurals": "複數形式錯誤 (Plural Form Error)",
    "unknown": "其他錯誤 (Other Error)"
}

def load_grammar_rules(rules_path=None):
    """Load grammar rules from pickle file"""
    # If path is specified, try to load from it
    if rules_path and os.path.exists(rules_path):
        try:
            print(f"Loading grammar rules from: {rules_path}")
            with open(rules_path, 'rb') as f:
                data = pickle.load(f)
                if isinstance(data, dict):
                    if "rules" in data:
                        return data
                    else:
                        return {"rules": data, "descriptions": ERROR_DESCRIPTIONS}
        except Exception as e:
            print(f"Error loading grammar rules: {e}", file=sys.stderr)
    
    # Try to load from default locations
    possible_paths = [
        os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'models', 'grammar_rules.pkl'),
        os.path.join('models', 'grammar_rules.pkl'),
        os.path.join(os.path.dirname(__file__), '..', 'models', 'grammar_rules.pkl'),
        os.path.join(os.getcwd(), 'models', 'grammar_rules.pkl')
    ]
    
    for path in possible_paths:
        if os.path.exists(path):
            try:
                print(f"Loading grammar rules from: {path}")
                with open(path, 'rb') as f:
                    data = pickle.load(f)
                    if isinstance(data, dict):
                        if "rules" in data:
                            return data
                        else:
                            return {"rules": data, "descriptions": ERROR_DESCRIPTIONS}
            except Exception as e:
                print(f"Error loading grammar rules from {path}: {e}", file=sys.stderr)
    
    # If all paths fail, return empty rules structure
    print("No grammar rules found, returning empty rules", file=sys.stderr)
    return {"rules": {}, "descriptions": ERROR_DESCRIPTIONS}

def check_grammar_issues(text, grammar_rules=None):
    """Check for grammar issues in the text using the provided rules"""
    # If rules not provided, load them
    if grammar_rules is None:
        grammar_rules = load_grammar_rules()
    
    # If no rules available after loading, try basic grammar patterns
    if not grammar_rules or not grammar_rules.get("rules"):
        return check_basic_english_grammar(text)
    
    issues = {}
    
    # Go through each error type and its rules
    for error_type, rules in grammar_rules.get("rules", {}).items():
        found_issues = []
        
        for rule in rules:
            if not rule.get('original'):
                continue
            
            original = rule['original']
            
            # Use different matching strategies for phrases vs. single words
            if ' ' in original:
                # For phrases, create a pattern allowing for flexible word boundaries
                pattern = r'(?i)\b' + re.escape(original).replace(r'\ ', r'\s+') + r'\b'
            else:
                # For single words, use simpler word boundary match
                pattern = r'(?i)\b' + re.escape(original) + r'\b'
            
            # Find all matches
            matches = re.findall(pattern, text)
            
            if matches:
                corrected = rule.get('corrected', [""])[0] if rule.get('corrected') else ""
                suggestion = f"'{original}' 可能應為 '{corrected}'"
                
                if suggestion not in found_issues:
                    found_issues.append(suggestion)
        
        if found_issues:
            issues[error_type] = found_issues
    
    # If no issues found with rules, try basic grammar checks
    if not issues:
        basic_issues = check_basic_english_grammar(text)
        if basic_issues:
            issues.update(basic_issues)
    
    return issues

def check_basic_english_grammar(text):
    """Basic English grammar check as a fallback mechanism"""
    issues = {}
    
    # Check tense errors
    tense_patterns = [
        (r'\bI go\b', "I went", "tense"),
        (r'\bwe go\b', "we went", "tense"),
        (r'\bsun shine\b', "sun shone", "tense"),
        (r'\bwe arrives\b', "we arrived", "tense"),
        (r'\bI laying\b', "I lay/I was laying", "tense"),
        (r'\bI eating\b', "I ate/I was eating", "tense"),
        (r'\bI reading\b', "I read/I was reading", "tense"),
        (r'\bshe reading\b', "she read/she was reading", "tense"),
        (r'\bIt were\b', "It was", "tense"),
        (r'\bI going\b', "I am going", "tense"),
        (r'\bfor take\b', "to take", "grammar")
    ]
    
    for pattern, correction, error_type in tense_patterns:
        if re.search(pattern, text, re.IGNORECASE):
            match = re.search(pattern, text, re.IGNORECASE).group()
            if error_type not in issues:
                issues[error_type] = []
            issues[error_type].append(f"'{match}' 可能應為 '{correction}'")
    
    # Check subject-verb agreement
    sv_patterns = [
        (r'\bweather were\b', "weather was", "subject_verb_agreement"),
        (r'\bsun setting\b', "sun was setting", "subject_verb_agreement"),
        (r'\bIt were\b', "It was", "subject_verb_agreement"),
        (r'\bthey is\b', "they are", "subject_verb_agreement"),
        (r'\bwe is\b', "we are", "subject_verb_agreement"),
        (r'\bhe are\b', "he is", "subject_verb_agreement"),
        (r'\bshe are\b', "she is", "subject_verb_agreement")
    ]
    
    for pattern, correction, error_type in sv_patterns:
        if re.search(pattern, text, re.IGNORECASE):
            match = re.search(pattern, text, re.IGNORECASE).group()
            if error_type not in issues:
                issues[error_type] = []
            issues[error_type].append(f"'{match}' 可能應為 '{correction}'")
    
    # Check article issues
    article_patterns = [
        (r'\bunder umbrella\b', "under an/the umbrella", "article"),
        (r'\ba [aeiou]\w+\b', "an [word]", "article"),
        (r'\ban [^aeiou\W]\w+\b', "a [word]", "article")
    ]
    
    for pattern, correction, error_type in article_patterns:
        for match in re.finditer(pattern, text, re.IGNORECASE):
            matched_text = match.group()
            if error_type not in issues:
                issues[error_type] = []
            
            if pattern == r'\ba [aeiou]\w+\b':
                word = re.search(r'a ([aeiou]\w+)', matched_text, re.IGNORECASE).group(1)
                issues[error_type].append(f"'a {word}' 可能應為 'an {word}'")
            elif pattern == r'\ban [^aeiou\W]\w+\b':
                word = re.search(r'an ([^aeiou\W]\w+)', matched_text, re.IGNORECASE).group(1)
                issues[error_type].append(f"'an {word}' 可能應為 'a {word}'")
            else:
                issues[error_type].append(f"'{matched_text}' 可能應為 '{correction}'")
    
    # Check plurals issues
    plural_patterns = [
        (r'\bfive hour\b', "five hours", "plurals"),
        (r'\btwo day\b', "two days", "plurals"),
        (r'\bour stuffs\b', "our stuff", "plurals"),
        (r'\bmany person\b', "many people", "plurals")
    ]
    
    for pattern, correction, error_type in plural_patterns:
        if re.search(pattern, text, re.IGNORECASE):
            match = re.search(pattern, text, re.IGNORECASE).group()
            if error_type not in issues:
                issues[error_type] = []
            issues[error_type].append(f"'{match}' 可能應為 '{correction}'")
    
    return issues

def analyze_text(text, rules_path=None):
    """Complete text analysis including grammar, statistics, and feedback"""
    # Load grammar rules
    grammar_rules = load_grammar_rules(rules_path)
    
    # Check grammar issues
    grammar_issues = check_grammar_issues(text, grammar_rules)
    
    # Calculate basic text statistics
    word_count = len(text.split())
    sentences = re.split(r'[.!?]+', text)
    sentence_count = len([s for s in sentences if s.strip()])
    
    # Calculate average sentence length
    if sentence_count > 0:
        avg_sentence_length = word_count / sentence_count
    else:
        avg_sentence_length = word_count  # If no sentences, use word count
    
    # Calculate unique words ratio (lexical diversity)
    unique_words = set(text.lower().split())
    if word_count > 0:
        lexical_diversity = len(unique_words) / word_count
    else:
        lexical_diversity = 0
    
    # Prepare response
    analysis = {
        "grammar_issues": grammar_issues,
        "statistics": {
            "word_count": word_count,
            "sentence_count": sentence_count,
            "avg_sentence_length": avg_sentence_length,
            "lexical_diversity": lexical_diversity
        },
        "descriptions": grammar_rules.get("descriptions", ERROR_DESCRIPTIONS)
    }
    
    return analysis

def generate_feedback(analysis, score=None, category=None):
    """Generate human-readable feedback based on the analysis"""
    feedback = []
    
    # Get statistics
    stats = analysis["statistics"]
    word_count = stats["word_count"]
    sentence_count = stats["sentence_count"]
    lexical_diversity = stats["lexical_diversity"]
    avg_sentence_length = stats["avg_sentence_length"]
    
    # Basic score information if provided
    if score is not None:
        if score >= 90:
            score_range = "優秀"
        elif score >= 80:
            score_range = "良好"
        elif score >= 70:
            score_range = "中上"
        elif score >= 60:
            score_range = "及格"
        else:
            score_range = "待加強"
        
        feedback.append(f"系統評分：{score}分（{score_range}）。")
    
    # Essay category if provided
    if category:
        category_mapping = {
            'narrative': '敘事文',
            'descriptive': '描述文',
            'argumentative': '論說文',
            'expository': '說明文',
            'compare_contrast': '比較對比文',
            'persuasive': '議論文',
            'reflective': '反思文',
            'critical_analysis': '批評性分析文'
        }
        
        category_name = category_mapping.get(category, category)
        feedback.append(f"這篇{category_name}包含約{word_count}個字，分為約{sentence_count}個句子。")
    else:
        feedback.append(f"這篇文章包含約{word_count}個字，分為約{sentence_count}個句子。")
    
    # Vocabulary richness analysis
    feedback.append("【詞彙豐富度】")
    if lexical_diversity > 0.7:
        feedback.append("文章使用了豐富多樣的詞彙，詞彙選擇精準。")
    elif lexical_diversity > 0.5:
        feedback.append("文章的詞彙多樣性良好，但仍可以進一步豐富。")
    elif lexical_diversity > 0.3:
        feedback.append("文章中有較多重複用詞，建議擴充詞彙，使用更多同義詞。")
    else:
        feedback.append("文章詞彙重複率較高，建議提升詞彙量。")
    
    # Grammar analysis
    feedback.append("【文法分析】")
    grammar_issues = analysis.get("grammar_issues", {})
    descriptions = analysis.get("descriptions", ERROR_DESCRIPTIONS)
    
    if grammar_issues:
        for issue_type, issues in grammar_issues.items():
            if issues:
                description = descriptions.get(issue_type, issue_type)
                feedback.append(f"發現可能的{description}:")
                for issue in issues[:2]:  # At most show 2 issues per type
                    feedback.append(f"- {issue}")
    else:
        feedback.append("未發現明顯的文法問題，文法使用良好。")
    
    # Sentence fluency analysis
    feedback.append("【句子流暢度】")
    if avg_sentence_length > 25:
        feedback.append("文章使用了較長的句子，部分句子可能影響閱讀流暢度。建議適當拆分長句，提高可讀性。")
    elif avg_sentence_length > 15:
        feedback.append("文章句子長度適中，閱讀流暢。")
    else:
        feedback.append("文章句子較短，可以嘗試增加一些複雜句型，提升文章層次感。")
    
    # Structure analysis
    feedback.append("【結構分析】")
    if sentence_count < 3:
        feedback.append("文章篇幅較短，建議增加內容，豐富論述。")
    else:
        paragraph_count = text.count('\n\n') + 1
        if paragraph_count < 2:
            feedback.append("文章段落劃分不明顯，建議適當分段，增加結構清晰度。")
        else:
            feedback.append("文章結構相對完整。")
    
    # Reminder
    feedback.append("這是系統基於文本特徵的初步評估，最終評分將由教師進行審核。")
    
    return "\n".join(feedback)

def main():
    """Main function to handle command line usage"""
    parser = argparse.ArgumentParser(description='Grammar Analysis Tool')
    parser.add_argument('-f', '--file', help='Text file to analyze')
    parser.add_argument('-t', '--text', help='Text content to analyze')
    parser.add_argument('-r', '--rules', help='Path to grammar rules file')
    parser.add_argument('-o', '--output', help='Output JSON file path')
    parser.add_argument('-s', '--score', type=int, help='Score for feedback generation')
    parser.add_argument('-c', '--category', help='Essay category')
    parser.add_argument('--feedback', action='store_true', help='Generate human-readable feedback')
    
    args = parser.parse_args()
    
    # Get text content
    text = ""
    if args.file:
        try:
            with open(args.file, 'r', encoding='utf-8') as f:
                text = f.read()
        except Exception as e:
            print(f"Error reading file: {e}", file=sys.stderr)
            return 1
    elif args.text:
        text = args.text
    else:
        print("No text provided. Use -f/--file or -t/--text", file=sys.stderr)
        return 1
    
    # Analyze text
    analysis = analyze_text(text, args.rules)
    
    # Generate feedback if requested
    if args.feedback:
        feedback = generate_feedback(analysis, args.score, args.category)
        analysis["feedback"] = feedback
    
    # Output results
    if args.output:
        try:
            with open(args.output, 'w', encoding='utf-8') as f:
                json.dump(analysis, f, ensure_ascii=False, indent=2)
            print(f"Analysis saved to {args.output}")
        except Exception as e:
            print(f"Error writing output file: {e}", file=sys.stderr)
            return 1
    else:
        # Print to stdout
        print(json.dumps(analysis, ensure_ascii=False, indent=2))
    
    return 0

if __name__ == "__main__":
    sys.exit(main())