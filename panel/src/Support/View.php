<?php

declare(strict_types=1);

namespace Panel\Support {

    final class View
    {
        public function __construct(private readonly string $dir)
        {
        }

        /** @param array<string, mixed> $data */
        public function render(string $template, array $data = [], ?string $layout = 'layout'): string
        {
            $content = $this->renderFile($template, $data);
            if ($layout === null) {
                return $content;
            }
            return $this->renderFile($layout, $data + ['content' => $content]);
        }

        /** @param array<string, mixed> $data */
        private function renderFile(string $template, array $data): string
        {
            $path = $this->dir . '/' . $template . '.php';
            if (!is_file($path)) {
                throw new \RuntimeException('Template not found: ' . $template);
            }
            extract($data, EXTR_SKIP);
            ob_start();
            require $path;
            return (string) ob_get_clean();
        }
    }
}

namespace {

    /** HTML-escape helper available to all templates. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
