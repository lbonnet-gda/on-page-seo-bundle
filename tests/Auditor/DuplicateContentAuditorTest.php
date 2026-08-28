<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Tests\Auditor;

use Lbonnet\OnPageSeoBundle\Auditor\DuplicateContentAuditor;
use Lbonnet\OnPageSeoBundle\Model\Issue;
use Lbonnet\OnPageSeoBundle\Model\IssueType;
use Lbonnet\OnPageSeoBundle\Model\PageAudit;
use Lbonnet\OnPageSeoBundle\Model\PageMetadata;
use PHPUnit\Framework\TestCase;

final class DuplicateContentAuditorTest extends TestCase
{
    private DuplicateContentAuditor $auditor;

    protected function setUp(): void
    {
        $this->auditor = new DuplicateContentAuditor();
    }

    public function testFlagsPagesSharingTheSameTitle(): void
    {
        $pages = [
            new PageAudit('https://example.com/a', new PageMetadata(title: 'Same Title')),
            new PageAudit('https://example.com/b', new PageMetadata(title: 'Same Title')),
            new PageAudit('https://example.com/c', new PageMetadata(title: 'Different Title')),
        ];

        $audited = $this->auditor->audit($pages);

        $this->assertTrue($this->hasIssueType($audited[0], IssueType::DuplicateTitle));
        $this->assertTrue($this->hasIssueType($audited[1], IssueType::DuplicateTitle));
        $this->assertFalse($this->hasIssueType($audited[2], IssueType::DuplicateTitle));
    }

    public function testFlagsPagesSharingTheSameDescription(): void
    {
        $pages = [
            new PageAudit('https://example.com/a', new PageMetadata(metaDescription: 'Same description.')),
            new PageAudit('https://example.com/b', new PageMetadata(metaDescription: 'Same description.')),
        ];

        $audited = $this->auditor->audit($pages);

        $this->assertTrue($this->hasIssueType($audited[0], IssueType::DuplicateDescription));
        $this->assertTrue($this->hasIssueType($audited[1], IssueType::DuplicateDescription));
    }

    public function testDoesNotFlagPagesWithMissingTitleOrDescription(): void
    {
        $pages = [
            new PageAudit('https://example.com/a', new PageMetadata()),
            new PageAudit('https://example.com/b', new PageMetadata()),
        ];

        $audited = $this->auditor->audit($pages);

        $this->assertFalse($this->hasIssueType($audited[0], IssueType::DuplicateTitle));
        $this->assertFalse($this->hasIssueType($audited[0], IssueType::DuplicateDescription));
        $this->assertFalse($this->hasIssueType($audited[1], IssueType::DuplicateTitle));
        $this->assertFalse($this->hasIssueType($audited[1], IssueType::DuplicateDescription));
    }

    public function testDoesNotFlagUniqueTitlesAndDescriptions(): void
    {
        $pages = [
            new PageAudit('https://example.com/a', new PageMetadata(title: 'A', metaDescription: 'Desc A')),
            new PageAudit('https://example.com/b', new PageMetadata(title: 'B', metaDescription: 'Desc B')),
        ];

        $audited = $this->auditor->audit($pages);

        $this->assertSame([], $audited[0]->issues);
        $this->assertSame([], $audited[1]->issues);
    }

    public function testPreservesExistingIssues(): void
    {
        $existingIssue = new Issue(IssueType::MissingH1, 'The page has no <h1> heading.');
        $pages = [
            new PageAudit('https://example.com/a', new PageMetadata(title: 'Same Title'), [$existingIssue]),
            new PageAudit('https://example.com/b', new PageMetadata(title: 'Same Title')),
        ];

        $audited = $this->auditor->audit($pages);

        $this->assertContains($existingIssue, $audited[0]->issues);
        $this->assertTrue($this->hasIssueType($audited[0], IssueType::DuplicateTitle));
    }

    private function hasIssueType(PageAudit $page, IssueType $type): bool
    {
        foreach ($page->issues as $issue) {
            if ($issue->type === $type) {
                return true;
            }
        }

        return false;
    }
}
