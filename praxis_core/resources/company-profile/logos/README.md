# Client logos for the company profile

Drop a PNG or an SVG per client here and it appears on the profile's Clients page, in filename order.
A number at the front sets the order and is not shown: `01 Fidson Healthcare.png` reads as "Fidson Healthcare".

This folder is the placeholder provider (`App\Support\CompanyProfile\DirectoryClientLogos`). PR 3 replaces
the binding in `AppServiceProvider` with the logo library managed from HQ, and this folder stops being read.

Nothing here is published on the website. A logo may only be used with the client's permission.
