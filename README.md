# hmp-assetic-bundle
Css Filter for Symfony 2's Assetic Bundle

Adds a single filter 'cssdump' which is intended to replace cssrewrite in certain use cases.

- Allows use of the `@AppBundle:css/mycss.css` style references
- Dumps css and attendant images and other assets without use of `app/console assets:install`
