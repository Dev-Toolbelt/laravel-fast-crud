<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Enum;

/**
 * Search filter operators for query building.
 *
 * These operators are used in the filter query parameter to specify
 * how values should be compared against database columns.
 *
 * @example
 * ```
 * GET /products?filter[name][like]=Samsung&filter[price][gte]=100&filter[status][in]=active,pending
 * ```
 */
enum SearchOperator: string
{
    /** Equality comparison (WHERE column = value) */
    case EQUAL = 'eq';

    /** Not equal comparison (WHERE column != value) */
    case NOT_EQUAL = 'neq';

    /** Value in list (WHERE column IN (values)) - comma-separated values */
    case IN = 'in';

    /** Value not in list (WHERE column NOT IN (values)) - comma-separated values */
    case NOT_IN = 'nin';

    /** Case-insensitive partial match (WHERE column ILIKE %value%) */
    case LIKE = 'like';

    /** Less than comparison (WHERE column < value) */
    case LESS_THAN = 'lt';

    /** Greater than comparison (WHERE column > value) */
    case GREATER_THAN = 'gt';

    /** Less than or equal comparison (WHERE column <= value) */
    case LESS_THAN_EQUAL = 'lte';

    /** Greater than or equal comparison (WHERE column >= value) */
    case GREATER_THAN_EQUAL = 'gte';

    /** Greater than OR null (WHERE column IS NULL OR column > value) */
    case GREATER_THAN_OR_NULL = 'gtn';

    /** Less than OR null (WHERE column IS NULL OR column < value) */
    case LESSER_THAN_OR_NULL = 'ltn';

    /** Between two values (WHERE column BETWEEN value1 AND value2) - comma-separated dates */
    case BETWEEN = 'btw';

    /** JSON column contains value (WHERE column @> value) */
    case JSON = 'json';

    /** Column is not null (WHERE column IS NOT NULL) */
    case NOT_NULL = 'nn';
}
