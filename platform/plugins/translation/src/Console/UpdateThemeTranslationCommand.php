<?php

namespace Botble\Translation\Console;

use BaseHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Finder\Finder;
use Theme;

#[AsCommand('cms:translations:update-theme-translations', 'Update theme translations')]
class UpdateThemeTranslationCommand extends Command
{
    public function handle(): int
    {
        $keys = $this->findTranslations(core_path());
        $keys += $this->findTranslations(package_path());
        $keys += $this->findTranslations(plugin_path());
        $keys += $this->findTranslations(theme_path(Theme::getThemeName()));
        ksort($keys);

        $data = json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        BaseHelper::saveFileData(theme_path(Theme::getThemeName() . '/lang/en.json'), $data, false);

        $this->info('Found ' . count($keys) . ' keys');

        return self::SUCCESS;
    }

    /**
     * @param string $path
     * @return array
     */
    public function findTranslations(string $path): array
    {
        $keys = [];

        $stringPattern =
            '[^\w]' .                                     // Must not have an alphanum before real method
            '(__)' .             // Must start with one of the functions
            '\(\s*' .                                       // Match opening parenthesis
            "(?P<quote>['\"])" .                            // Match " or ' and store in {quote}
            '(?P<string>(?:\\\k{quote}|(?!\k{quote}).)*)' . // Match any string that can be {quote} escaped
            '\k{quote}' .                                   // Match " or ' previously matched
            '\s*[\),]';                                    // Close parentheses or new parameter

        // Find all PHP + Twig files in the app folder, except for storage
        $finder = new Finder();
        $finder->in($path)->name('*.php')->files();

        /**
         * @var \Symfony\Component\Finder\SplFileInfo $file
         */
        foreach ($finder as $file) {
            if (! preg_match_all('/' . $stringPattern . '/siU', $file->getContents(), $matches)) {
                continue;
            }

            foreach ($matches['string'] as $key) {
                if (preg_match('/(^[a-zA-Z0-9_-]+([.][^\)\ ]+)+$)/siU', $key, $groupMatches) && ! Str::contains(
                    $key,
                    '...'
                )) {
                    // Do nothing, it has to be treated as a group
                    continue;
                }

                // Skip keys which contain namespacing characters, unless they also contain a space, which makes it JSON.
                if (! (Str::contains($key, '::') && Str::contains($key, '.')) || Str::contains($key, ' ')) {
                    $keys[trim($key)] = $key;
                }
            }
        }

        return array_unique($keys);
    }
}
