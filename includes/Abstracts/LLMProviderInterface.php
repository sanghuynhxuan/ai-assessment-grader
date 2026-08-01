<?php

namespace LearnDashAIGrader\Abstracts;

interface LLMProviderInterface {


    public function get_name();


    public function grade( $question_text, $student_response, $correct_answer, $options = [] );


    public function validate_connection();
}