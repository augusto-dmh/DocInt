<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Scanning = 'scanning';
    case ScanPassed = 'scan_passed';
    case Extracting = 'extracting';
    case Classifying = 'classifying';
    case ReadyForReview = 'ready_for_review';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ScanFailed = 'scan_failed';
    case ExtractionFailed = 'extraction_failed';
    case ClassificationFailed = 'classification_failed';
}
