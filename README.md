# Agencia RSE Staging Guard

Plugin de WordPress para copias de staging y desarrollo. Por defecto:

- **Saca la web de los buscadores**: noindex en todas las páginas (WordPress, Yoast, Rank Math, SEOPress), cabecera `X-Robots-Tag: noindex, nofollow` en todas las respuestas de PHP. El robots.txt se deja abierto a propósito para que Google vea el noindex.
- **Bloquea la medición real**: PixelYourSite, GTM4WP, Site Kit, MonsterInsights, Código personalizado de Elementor Pro y, como red, cualquier `<script>`/`<noscript>` de GTM, GA, Google Ads, Meta, Clarity, Hotjar, LinkedIn, TikTok o Bing en el HTML final.
- **Pausa los webhooks de WooCommerce** y **los pings y pingbacks**.
- **Etiqueta STAGING** en rojo en la barra de administración.

El **correo** no se toca salvo que lo actives: entonces se descarta o se redirige todo a una dirección (con el destinatario original en el asunto). Ajustes en *Herramientas → Staging Guard*.

No cambia ningún ajuste guardado de otros plugins: si la base de datos vuelve a producción, todo sigue como estaba. En un dominio que parece de producción no hace nada y avisa.

## Cuándo actúa (modo automático)

- Dominio con pinta de staging: `staging.`, `dev.`, `test.`, `demo.`, `preprod.`…, `localhost`, IP, `.local`, `.test` o los dominios de staging de WP Engine, Kinsta, Pantheon, Flywheel, Cloudways, WordPress.com, InstaWP, SiteGround (`sg-host.com`) y GoDaddy.
- `WP_ENVIRONMENT_TYPE` = `staging`, `development` o `local`.
- «Disuadir a los motores de búsqueda» marcado.

«Siempre activo» se guarda junto al dominio donde se eligió: si la BD se copia a otro dominio, vuelve a automático. La constante `AGRSE_STAGING` (`true`/`false`) en `wp-config.php` manda sobre todo.

## Empaquetar

```bash
python ~/.claude/skills/wp-plugin-zip/scripts/wp_zip.py build . dist/agencia-rse-staging-guard-<versión>.zip
```

Sin `README.md` ni `wporg-i18n/` en el zip de WordPress.org (copiar `agencia-rse-staging-guard.php uninstall.php readme.txt LICENSE` a una carpeta `agencia-rse-staging-guard` y empaquetar esa).

## Traducciones

Textos en inglés en el código; el español se sube a translate.wordpress.org con `python wporg-i18n/build_po.py` (aborta si falta alguna cadena).

Licencia GPLv2 o posterior. Hecho por [AGENCIA RSE](https://agenciarse.com/).
