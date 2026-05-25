<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PraeviseoPublishedPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 190, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $contentHtml = '';

    #[ORM\Column(type: Types::JSON)]
    private array $faqJson = [];

    #[ORM\Column(type: Types::JSON)]
    private array $schemaJson = [];

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $canonicalUrl = null;

    #[ORM\Column]
    private bool $noindex = false;

    public function getSlug(): string { return $this->slug; }
    public function getTitle(): string { return $this->title; }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function getContentHtml(): string { return $this->contentHtml; }
    public function getCanonicalUrl(): ?string { return $this->canonicalUrl; }
    public function isNoindex(): bool { return $this->noindex; }
    public function setSlug(string $slug): void { $this->slug = $slug; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function setMetaDescription(?string $metaDescription): void { $this->metaDescription = $metaDescription; }
    public function setContentHtml(string $contentHtml): void { $this->contentHtml = $contentHtml; }
    public function setFaqJson(array $faqJson): void { $this->faqJson = $faqJson; }
    public function setSchemaJson(array $schemaJson): void { $this->schemaJson = $schemaJson; }
    public function setCanonicalUrl(?string $canonicalUrl): void { $this->canonicalUrl = $canonicalUrl; }
    public function setNoindex(bool $noindex): void { $this->noindex = $noindex; }
}
