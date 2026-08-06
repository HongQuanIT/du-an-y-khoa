<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

/** Faceted catalogs that can be assigned to one question. */
enum QuestionScopeType: string
{
    case Exam = 'exam';
    case Article = 'article';
    case Symptom = 'symptom';
}
