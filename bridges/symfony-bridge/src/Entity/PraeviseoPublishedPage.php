<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'praeviseo_published_pages')]
class PraeviseoPublishedPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 190)]
    private string $praeviseoSiteId = '';

    #[ORM\Column]
    private int $externalPageId = 0;

    #[ORM\Column(length: 190, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $h1 = null;

    #[ORM\Column(length: 255, nullable: true)]
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

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $cluster = null;

    #[ORM\Column]
    private bool $isNoindex = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageAlt = null;

    #[ORM\Column(length: 40)]
    private string $publicationState = 'published';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastPublishedAt = null;

    public function getSlug(): string { return $this->slug; }
    public function getTitle(): string { return $this->title; }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function getContentHtml(): string { return $this->contentHtml; }
    public function getCanonicalUrl(): ?string { return $this->canonicalUrl; }
    public function isNoindex(): bool { return $this->isNoindex; }
    public function getLiveUrl(): ?string { return $this->liveUrl; }
    public function getLastPublishedAt(): ?\DateTimeImmutable { return $this->lastPublishedAt; }
    public function setPraeviseoSiteId(string $praeviseoSiteId): void { $this->praeviseoSiteId = $praeviseoSiteId; }
    public function setExternalPageId(int $externalPageId): void { $this->externalPageId = $externalPageId; }
    public function setSlug(string $slug): void { $this->slug = $slug; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function setH1(?string $h1): void { $this->h1 = $h1; }
    public function setMetaDescription(?string $metaDescription): void { $this->metaDescription = $metaDescription; }
    public function setContentHtml(string $contentHtml): void { $this->contentHtml = $contentHtml; }
    public function setFaqJson(array $faqJson): void { $this->faqJson = $faqJson; }
    public function setSchemaJson(array $schemaJson): void { $this->schemaJson = $schemaJson; }
    public function setInternalLinksJson(array $internalLinksJson): void { $this->internalLinksJson = $internalLinksJson; }
    public function setCanonicalUrl(?string $canonicalUrl): void { $this->canonicalUrl = $canonicalUrl; }
    public function setLiveUrl(?string $liveUrl): void { $this->liveUrl = $liveUrl; }
    public function setCluster(?string $cluster): void { $this->cluster = $cluster; }
    public function setIsNoindex(bool $isNoindex): void { $this->isNoindex = $isNoindex; }
    public function setImagePath(?string $imagePath): void { $this->imagePath = $imagePath; }
    public function setImageAlt(?string $imageAlt): void { $this->imageAlt = $imageAlt; }
    public function setPublicationState(string $publicationState): void { $this->publicationState = $publicationState; }
    public function setLastPublishedAt(?\DateTimeImmutable $lastPublishedAt): void { $this->lastPublishedAt = $lastPublishedAt; }
}
