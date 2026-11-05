<?php

namespace App\Support\CompanyProfile;

/**
 * Where the logo wall on the Clients page comes from (company-profile/CONTRACT.md, `clients.logos`).
 *
 * PR 1 ships DirectoryClientLogos, which simply lists the files the lead chat drops in
 * resources/company-profile/logos/. PR 3 replaces the binding in AppServiceProvider with the logo library
 * managed from HQ. Nothing else in the engine knows the difference.
 */
interface ClientLogos
{
    /**
     * Every logo to show, in the order it should appear.
     *
     * @return list<array{name: string, path: string}>
     */
    public function all(): array;
}
