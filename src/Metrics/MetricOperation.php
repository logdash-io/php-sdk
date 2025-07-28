<?php

namespace Logdash\Metrics;

enum MetricOperation: string
{
    case SET = 'set';
    case CHANGE = 'change';
}
