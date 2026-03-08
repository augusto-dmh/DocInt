<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case ReadyForReview = 'ready_for_review';
    case Approved = 'approved';
}
