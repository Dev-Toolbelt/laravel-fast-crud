<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Enum;

enum SearchOperator: string
{
    case EQUAL = 'eq';
    case NOT_EQUAL = 'neq';
    case IN = 'in';
    case NOT_IN = 'nin';
    case LIKE = 'like';
    case LESS_THAN = 'lt';
    case GREATER_THAN = 'gt';
    case LESS_THAN_EQUAL = 'lte';
    case GREATER_THAN_EQUAL = 'gte';
    case GREATER_THAN_OR_NULL = 'gtn';
    case LESSER_THAN_OR_NULL = 'ltn';
    case BETWEEN = 'btw';
    case JSON = 'json';
    case NOT_NULL = 'nn';
}
