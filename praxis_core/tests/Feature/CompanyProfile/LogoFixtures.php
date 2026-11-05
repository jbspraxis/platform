<?php

namespace Tests\Feature\CompanyProfile;

/**
 * The smallest real files of each kind the logo library takes, so a test can upload one without a picture
 * file in the repository. They are genuine bytes, not placeholders: the library checks the magic numbers.
 */
trait LogoFixtures
{
    /** A 1x1 transparent PNG */
    protected function png(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }

    /** A 1x1 JPEG on white */
    protected function jpeg(): string
    {
        return 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            .'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAA'
            .'AQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIh'
            .'MUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpT'
            .'VFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5'
            .'usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iii'
            .'gD//2Q==';
    }

    /** A 1x1 WebP (lossy, VP8) */
    protected function webp(): string
    {
        return 'data:image/webp;base64,UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';
    }

    /** A plain SVG mark, with a script and a remote image in it, so a test can check they are taken out */
    protected function svg(string $extra = ''): string
    {
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 40">'
            .'<title>Mark</title><rect x="0" y="0" width="100" height="40" fill="#0C8B9B"/>'
            .$extra.'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
