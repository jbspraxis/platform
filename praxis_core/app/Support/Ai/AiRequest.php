<?php

namespace App\Support\Ai;

/**
 * What a provider is asked: the system blocks (the company brief, then the opportunity context; each is a cache
 * breakpoint, so redrafts reread them at a tenth of the price), then one user message that holds everything that
 * changes (task, language, the lead's instructions, the material). The answer must follow $schema.
 *
 * $attachments: files the model reads with the message (a PDF as a document block, a JPG or PNG as an image block),
 * each ['media_type' => …, 'data' => base64, 'pages' => n]. Company documents send the uploaded certificate this way.
 */
final class AiRequest
{
    /** Claude reads a PDF page as its text and an image of it: about 1,500 to 3,000 tokens, so the budget counts 3,000 */
    public const TOKENS_PER_PDF_PAGE = 3000;

    /** An image is resized to fit the model's limit first; the budget counts the largest it can be */
    public const TOKENS_PER_IMAGE = 5000;

    /**
     * @param  list<string>  $system
     * @param  list<array{media_type: string, data: string, pages?: int}>  $attachments
     */
    public function __construct(
        public readonly string $purpose,
        public readonly string $model,
        public readonly string $effort,
        public readonly int $maxTokens,
        public readonly array $system,
        public readonly string $prompt,
        public readonly array $schema,
        public readonly bool $stream = false,
        public readonly int $expectedChars = 4000,
        public readonly array $attachments = [],
    ) {}

    /** Characters sent, for the budget's estimate of the input tokens (attachments are counted apart) */
    public function inputChars(): int
    {
        return array_sum(array_map('mb_strlen', $this->system)) + mb_strlen($this->prompt) + mb_strlen((string) json_encode($this->schema));
    }

    /** The most tokens the attachments can take */
    public function attachmentTokens(): int
    {
        return array_sum(array_map(fn (array $a) => $a['media_type'] === 'application/pdf'
            ? self::TOKENS_PER_PDF_PAGE * max(1, (int) ($a['pages'] ?? 1))
            : self::TOKENS_PER_IMAGE, $this->attachments));
    }
}
