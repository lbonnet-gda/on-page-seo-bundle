<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Model;

final class Issue
{
    public function __construct(
        public readonly IssueType $type,
        public readonly string $message,
    ) {
    }
}
