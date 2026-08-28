<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Auditor;

use Lbonnet\OnPageSeoBundle\Model\Issue;
use Lbonnet\OnPageSeoBundle\Model\IssueType;
use Lbonnet\OnPageSeoBundle\Model\PageMetadata;

final class PageAuditor implements PageAuditorInterface
{
    public function __construct(
        private readonly int $maxTitleLength = 60,
        private readonly int $maxDescriptionLength = 160,
    ) {
    }

    public function audit(PageMetadata $metadata): array
    {
        return [
            ...$this->auditTitle($metadata),
            ...$this->auditDescription($metadata),
            ...$this->auditHeadings($metadata),
            ...$this->auditImages($metadata),
        ];
    }

    /**
     * @return list<Issue>
     */
    private function auditTitle(PageMetadata $metadata): array
    {
        if ($metadata->title === null) {
            return [new Issue(IssueType::MissingTitle, 'The page has no <title> element.')];
        }

        if (mb_strlen($metadata->title) > $this->maxTitleLength) {
            return [
                new Issue(
                    IssueType::TitleTooLong,
                    sprintf(
                        'The title is %d characters long, longer than the recommended %d.',
                        mb_strlen($metadata->title),
                        $this->maxTitleLength
                    ),
                ),
            ];
        }

        return [];
    }

    /**
     * @return list<Issue>
     */
    private function auditDescription(PageMetadata $metadata): array
    {
        if ($metadata->metaDescription === null) {
            return [new Issue(IssueType::MissingDescription, 'The page has no meta description.')];
        }

        if (mb_strlen($metadata->metaDescription) > $this->maxDescriptionLength) {
            return [
                new Issue(
                    IssueType::DescriptionTooLong,
                    sprintf(
                        'The meta description is %d characters long, longer than the recommended %d.',
                        mb_strlen($metadata->metaDescription),
                        $this->maxDescriptionLength
                    ),
                ),
            ];
        }

        return [];
    }

    /**
     * @return list<Issue>
     */
    private function auditHeadings(PageMetadata $metadata): array
    {
        $count = count($metadata->headings);

        if ($count === 0) {
            return [new Issue(IssueType::MissingH1, 'The page has no <h1> heading.')];
        }

        if ($count > 1) {
            return [
                new Issue(
                    IssueType::MultipleH1,
                    sprintf('The page has %d <h1> headings, expected exactly one.', $count),
                ),
            ];
        }

        return [];
    }

    /**
     * @return list<Issue>
     */
    private function auditImages(PageMetadata $metadata): array
    {
        return array_map(
            static fn(string $src): Issue => new Issue(
                IssueType::ImageMissingAlt,
                sprintf('Image "%s" is missing an alt attribute.', $src),
            ),
            $metadata->imagesMissingAlt,
        );
    }
}
