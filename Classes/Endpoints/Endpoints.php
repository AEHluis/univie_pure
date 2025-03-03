<?php

namespace Univie\UniviePure\Endpoints;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class Endpoints
{


    protected function getNestedArrayValue($array, string $path, $default = null)
    {
        // Return default if input is not an array
        if (!is_array($array)) {
            return $default;
        }

        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            // Check if current is an array and if the key exists
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }


    protected function arrayKeyExists($key, $array): bool
    {
        return is_array($array) && array_key_exists($key, $array);
    }


    protected function getArrayValue($array, $key, $default = null)
    {
        return $this->arrayKeyExists($key, $array) ? $array[$key] : $default;
    }


    public function getSearchXml($settings): string
    {
        // Use getArrayValue from parent class to safely access array keys
        $terms = $this->getArrayValue($settings, 'narrowBySearch', '');

        // Combine the backend filter and the frontend form:
        $filter = $this->getArrayValue($settings, 'filter', '');
        if ($filter) {
            $terms .= ' ' . $filter;
        }

        return '<searchString>' . trim($terms) . '</searchString>';
    }


    protected function getOrderingXml(?string $order = null, string $default = '-startDate'): string
    {
        if (empty($order)) {
            $order = $default;
        }
        return '<orderings><ordering>' . $order . '</ordering></orderings>';
    }


    protected function transformRenderingHtml(string $html, array $options = []): string
    {
        // Example “baseline” transformations:
        $html = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $html);
        $html = preg_replace('#<p><\/p>#is', '', $html);
        $html = str_replace('<br />', ' ', $html);

        // If your child class needs special additional replacements:
        if (!empty($options['removeTypeParagraph'])) {
            $html = preg_replace('#<p class="type">(.*?)</p>#is', '', $html);
        }

        return $html;
    }


    protected function calculateOffset(int $pageSize, int $currentPage): int
    {
        return ($currentPage > 0)
            ? (($currentPage - 1) * $pageSize)
            : 0;
    }

}
