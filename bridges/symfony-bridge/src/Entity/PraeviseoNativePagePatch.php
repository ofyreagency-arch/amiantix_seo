<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'praeviseo_native_page_patches')]
#[ORM\UniqueConstraint(name: 'praeviseo_native_page_patch_site_path', columns: ['praeviseo_site_id', 'target_path'])]
class PraeviseoNativePagePatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $praeviseoSiteId = '';

    #[ORM\Column]
    private int $externalPageId = 0;

    #[ORM\Column(length: 255)]
    private string $targetPath = '/';

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $h1 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $contentHtml = '';

    #[ORM\Column(type: Types::JSON)]
    private array $faqJson = [];

    #[ORM\Column(type: Types::JSON)]
    private array $schemaJson = [];

    #[ORM\Column(type: Types::JSON)]
    private array $internalLinksJson = [];

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $canonicalUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $liveUrl = null;

    #[ORM\Column(length: 40)]
    private string $publicationState = 'published';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastPublishedAt = null;

    public function getTargetPath(): string
    {
        return $this->targetPath;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function getContentHtml(): string
    {
        return $this->contentHtml;
    }

    /**
     * @return array<int,mixed>
     */
    public function getFaqJson(): array
    {
        return $this->faqJson;
    }

    public function getPublicationState(): string
    {
        return $this->publicationState;
    }

    public function getLiveUrl(): ?string
    {
        return $this->liveUrl;
    }

    public function setPraeviseoSiteId(string $praeviseoSiteId): void
    {
        $this->praeviseoSiteId = $praeviseoSiteId;
    }

    public function setExternalPageId(int $externalPageId): void
    {
        $this->externalPageId = $externalPageId;
    }

    public function setTargetPath(string $targetPath): void
    {
        $this->targetPath = $targetPath;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setH1(?string $h1): void
    {
        $this->h1 = $h1;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function setContentHtml(string $contentHtml): void
    {
        $this->contentHtml = $contentHtml;
    }

    public function setFaqJson(array $faqJson): void
    {
        $this->faqJson = $faqJson;
    }

    public function setSchemaJson(array $schemaJson): void
    {
        $this->schemaJson = $schemaJson;
    }

    public function setInternalLinksJson(array $internalLinksJson): void
    {
        $this->internalLinksJson = $internalLinksJson;
    }

    public function setCanonicalUrl(?string $canonicalUrl): void
    {
        $this->canonicalUrl = $canonicalUrl;
    }

    public function setLiveUrl(?string $liveUrl): void
    {
        $this->liveUrl = $liveUrl;
    }

    public function setPublicationState(string $publicationState): void
    {
        $this->publicationState = $publicationState;
    }

    public function setLastPublishedAt(?\DateTimeImmutable $lastPublishedAt): void
    {
        $this->lastPublishedAt = $lastPublishedAt;
    }
}
