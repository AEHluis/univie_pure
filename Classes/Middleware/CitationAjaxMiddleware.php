<?php

declare(strict_types=1);

namespace Univie\UniviePure\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use Univie\UniviePure\Endpoints\ResearchOutput;
use Univie\UniviePure\Utility\CommonUtilities;

/**
 * Frontend middleware for lazy-loading citation styles via AJAX
 * Handles requests with eID=univie_pure_citation parameter
 */
class CitationAjaxMiddleware implements MiddlewareInterface
{
    private const ALLOWED_STYLES = ['standard', 'harvard', 'apa', 'vancouver', 'author', 'bibtex'];

    public function __construct(
        private readonly ResearchOutput $researchOutput
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $eID = $queryParams['eID'] ?? null;

        // Only handle our specific eID
        if ($eID !== 'univie_pure_citation') {
            return $handler->handle($request);
        }

        return $this->handleCitationRequest($request);
    }

    /**
     * Handle the citation AJAX request
     */
    private function handleCitationRequest(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $uuid = trim($queryParams['uuid'] ?? '');
        $style = trim($queryParams['style'] ?? '');
        $locale = trim($queryParams['locale'] ?? 'de_DE');

        // Validate UUID format
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $uuid)) {
            return new JsonResponse(['error' => 'Invalid UUID'], 400);
        }

        // Validate style
        if (!in_array($style, self::ALLOWED_STYLES, true)) {
            return new JsonResponse(['error' => 'Invalid citation style'], 400);
        }

        // Validate locale
        if (!in_array($locale, ['de_DE', 'en_GB'], true)) {
            $locale = 'de_DE';
        }

        try {
            if ($style === 'bibtex') {
                $response = $this->researchOutput->getBibtex($uuid, $locale);
            } else {
                $response = $this->researchOutput->getCitationRendering($uuid, $style, $locale);
            }

            $content = $this->extractCitationContent($response);

            if ($content === '') {
                return new JsonResponse(['error' => 'Citation not available'], 404);
            }

            // Normalize preformatted content (bibtex)
            $isPreformatted = ($style === 'bibtex');
            if ($isPreformatted) {
                $content = $this->normalizePreformattedCitation($content, $style);
            }

            return new JsonResponse([
                'success' => true,
                'style' => $style,
                'content' => $content,
                'isPreformatted' => $isPreformatted,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Failed to fetch citation'], 500);
        }
    }

    /**
     * Extract citation content from API response
     */
    private function extractCitationContent(mixed $response): string
    {
        if (is_string($response)) {
            $content = trim($response);
            return $this->isRendererErrorPayload($content) ? '' : $content;
        }

        if (!is_array($response)) {
            return '';
        }

        $paths = [
            'renderings.rendering',
            'renderings.0.html',
            'renderings.rendering.0.html',
            'rendering',
            'data',
        ];

        foreach ($paths as $path) {
            $value = CommonUtilities::getNestedArrayValue($response, $path, null);
            $text = $this->flattenCitationValue($value);
            if ($text !== '') {
                return $this->isRendererErrorPayload($text) ? '' : $text;
            }
        }

        return '';
    }

    /**
     * Flatten nested citation value to string
     */
    private function flattenCitationValue(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (!is_array($value)) {
            return '';
        }
        if (isset($value['html']) && is_string($value['html'])) {
            return trim($value['html']);
        }

        $parts = [];
        foreach ($value as $item) {
            $piece = $this->flattenCitationValue($item);
            if ($piece !== '') {
                $parts[] = $piece;
            }
        }
        return trim(implode("\n", $parts));
    }

    /**
     * Check if response is an error payload
     */
    private function isRendererErrorPayload(string $content): bool
    {
        $trimmed = ltrim($content);
        return str_starts_with($trimmed, 'Unknown render style');
    }

    /**
     * Normalize preformatted citation content (bibtex, ris)
     */
    private function normalizePreformattedCitation(string $content, string $styleId): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $content);
        $text = preg_replace('#</p>\s*<p[^>]*>#i', "\n\n", (string)$text);
        $text = strip_tags((string)$text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        if ($styleId === 'bibtex') {
            $text = $this->formatBibtexForReadability($text);
        }

        return $text;
    }

    /**
     * Format BibTeX for better readability
     */
    private function formatBibtexForReadability(string $bibtex): string
    {
        $text = trim($bibtex);
        if (!str_starts_with($text, '@')) {
            return $text;
        }

        $text = preg_replace('/^(@[^{]+\{[^,]+),\s{2,}/', "$1,\n  ", $text) ?? $text;
        $text = preg_replace('/,\s{2,}([a-zA-Z_][a-zA-Z0-9_]*\s*=)/', ",\n  $1", $text) ?? $text;
        $text = preg_replace('/\n\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*/', "\n  $1 = ", $text) ?? $text;
        $text = preg_replace('/,\s*}$/', "\n}", $text) ?? $text;

        return $text;
    }
}
