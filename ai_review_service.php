<?php
// ai_review_service.php
class AIReviewService {
    /**
     * 使用 AI 批改作文
     * @param string $content 作文內容
     * @param string $category 作文類型
     * @return array 批改結果，包含 'feedback' 和 'score'
     */
    public function reviewEssay($content, $category) {
        // 這裡應該調用實際的 AI 服務 API
        // 以下是模擬的結果
        $feedback = $this->generateFeedback($content, $category);
        $score = $this->calculateScore($content, $category);
        
        return [
            'feedback' => $feedback,
            'score' => $score
        ];
    }
    
    /**
     * 生成評語
     * @param string $content 作文內容
     * @param string $category 作文類型
     * @return string 評語
     */
    private function generateFeedback($content, $category) {
        // 模擬評語生成
        $feedbacks = [
            "這篇作文結構清晰，語言流暢，表達了深刻的見解。",
            "文章邏輯性強，但部分論述可以更加深入。",
            "整體而言，這是一篇很有思考深度的作文，能夠從多角度分析問題。",
            "您的語言表達能力很強，但有些觀點可以更加具體。",
            "這篇作文觀點鮮明，但支持論據可以更加充分。"
        ];
        
        return $feedbacks[array_rand($feedbacks)];
    }
    
    /**
     * 計算評分
     * @param string $content 作文內容
     * @param string $category 作文類型
     * @return int 評分
     */
    private function calculateScore($content, $category) {
        // 模擬評分計算
        $base_score = 70;
        $length_bonus = min(10, strlen($content) / 200);
        $random_factor = rand(-5, 10);
        
        return min(98, max(60, $base_score + $length_bonus + $random_factor));
    }
}
?>