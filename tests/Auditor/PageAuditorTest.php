<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Tests\Auditor;

use Lbonnet\OnPageSeoBundle\Auditor\PageAuditor;
use Lbonnet\OnPageSeoBundle\Model\Issue;
use Lbonnet\OnPageSeoBundle\Model\IssueType;
use Lbonnet\OnPageSeoBundle\Model\PageMetadata;
use PHPUnit\Framework\TestCase;

final class PageAuditorTest extends TestCase
{
    private PageAuditor $auditor;

    protected function setUp(): void
    {
        $this->auditor = new PageAuditor(maxTitleLength: 20, maxDescriptionLength: 40);
    }

    public function testFlagsMissingTitleAndDescription(): void
    {
        $metadata = new PageMetadata(headings: ['Only heading']);

        $issues = $this->auditor->audit($metadata);

        $this->assertContainsIssueType(IssueType::MissingTitle, $issues);
        $this->assertContainsIssueType(IssueType::MissingDescription, $issues);
    }

    public function testFlagsTitleAndDescriptionTooLong(): void
    {
        $metadata = new PageMetadata(
            title: 'This title is definitely way too long',
            metaDescription: 'This meta description is also going to be far too long for the configured limit',
            headings: ['Heading'],
        );

        $issues = $this->auditor->audit($metadata);

        $this->assertContainsIssueType(IssueType::TitleTooLong, $issues);
        $this->assertContainsIssueType(IssueType::DescriptionTooLong, $issues);
    }

    public function testDoesNotFlagTitleAndDescriptionWithinLimits(): void
    {
        $metadata = new PageMetadata(
            title: 'Short title',
            metaDescription: 'Short description.',
            headings: ['Heading'],
        );

        $issues = $this->auditor->audit($metadata);

        $this->assertIssueTypeAbsent(IssueType::MissingTitle, $issues);
        $this->assertIssueTypeAbsent(IssueType::TitleTooLong, $issues);
        $this->assertIssueTypeAbsent(IssueType::MissingDescription, $issues);
        $this->assertIssueTypeAbsent(IssueType::DescriptionTooLong, $issues);
    }

    public function testFlagsMissingH1(): void
    {
        $metadata = new PageMetadata(title: 'Title', metaDescription: 'Description.', headings: []);

        $issues = $this->auditor->audit($metadata);

        $this->assertContainsIssueType(IssueType::MissingH1, $issues);
    }

    public function testFlagsMultipleH1(): void
    {
        $metadata = new PageMetadata(title: 'Title', metaDescription: 'Description.', headings: ['First', 'Second']);

        $issues = $this->auditor->audit($metadata);

        $this->assertContainsIssueType(IssueType::MultipleH1, $issues);
    }

    public function testFlagsEachImageMissingAlt(): void
    {
        $metadata = new PageMetadata(
            title: 'Title',
            metaDescription: 'Description.',
            headings: ['Heading'],
            imagesMissingAlt: ['/a.png', '/b.png'],
        );

        $issues = $this->auditor->audit($metadata);
        $imageIssues = array_values(
            array_filter($issues, static fn(Issue $issue): bool => $issue->type === IssueType::ImageMissingAlt)
        );

        $this->assertCount(2, $imageIssues);
    }

    /**
     * @param list<Issue> $issues
     */
    private function assertContainsIssueType(IssueType $type, array $issues): void
    {
        $types = array_map(static fn(Issue $issue): IssueType => $issue->type, $issues);

        $this->assertContains($type, $types);
    }

    /**
     * @param list<Issue> $issues
     */
    private function assertIssueTypeAbsent(IssueType $type, array $issues): void
    {
        $types = array_map(static fn(Issue $issue): IssueType => $issue->type, $issues);

        $this->assertNotContains($type, $types);
    }
}
