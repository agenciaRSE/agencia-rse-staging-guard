"""
Builds the es_ES .po to import into translate.wordpress.org (project agencia-rse-staging-guard, "Stable" code strings).

    python wporg-i18n/build_po.py

Every msgid must match the English source exactly; the script aborts if agencia-rse-staging-guard.php
has a translatable string with no translation here.
"""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

T = {
    # Plugin header.
    'Agencia RSE Staging Guard': 'Agencia RSE Staging Guard',
    'For staging and development copies of a site. Keeps the whole site out of search engines and stops the real analytics and ad tags (GTM, GA4, Google Ads, Meta…), WooCommerce webhooks and pings. Optionally blocks or redirects outgoing email. It never changes saved settings and does nothing on a domain that looks like production.':
        'Para copias de staging y desarrollo. Saca toda la web de los buscadores y corta la medición y las etiquetas de publicidad reales (GTM, GA4, Google Ads, Meta…), los webhooks de WooCommerce y los pings. Si quieres, bloquea o redirige el correo saliente. No cambia ningún ajuste guardado y no hace nada en un dominio que parece de producción.',
    'AGENCIA RSE': 'AGENCIA RSE',
    'https://agenciarse.com/': 'https://agenciarse.com/',
    'https://github.com/agenciaRSE/agencia-rse-staging-guard': 'https://github.com/agenciaRSE/agencia-rse-staging-guard',
    # Code.
    '"Discourage search engines" is checked in Settings > Reading': 'está marcado «Disuadir a los motores de búsqueda» en Ajustes > Lectura',
    '%s looks like a live site. If it is, deactivate the plugin. If it is a staging copy, choose "Always on" in the settings.':
        '%s parece una web en producción. Si lo es, desactiva el plugin. Si es un staging, elige «Siempre activo» en los ajustes.',
    'Action': 'Acción',
    'Active on this site.': 'Activo en esta web.',
    'Acts when the domain looks like staging (staging., dev., test., .local, localhost, host staging domains…), when WP_ENVIRONMENT_TYPE is not production, or when "Discourage search engines" is checked.':
        'Actúa si el dominio parece de staging (staging., dev., test., .local, localhost, dominios de staging de los hostings…), si WP_ENVIRONMENT_TYPE no es production o si está marcado «Disuadir a los motores de búsqueda».',
    'Always on for %s': 'Siempre activo en %s',
    'Automatic (recommended)': 'Automático (recomendado)',
    'Block analytics and ad tags': 'Bloquear la medición y las etiquetas de publicidad',
    'Blocked': 'Bloqueado',
    'Date': 'Fecha',
    'Email': 'Correo',
    'Empty: messages are dropped. With an address: they all go there, with the original recipient in the subject and no Cc or Bcc.':
        'Vacío: los mensajes se descartan. Con una dirección: van todos allí, con el destinatario original en el asunto y sin Cc ni Cco.',
    'GTM, GA4, Google Ads, Meta, Clarity, Hotjar, LinkedIn, TikTok and Bing: PixelYourSite, GTM4WP, Site Kit, MonsterInsights, Elementor custom code and any of those tags left in the HTML.':
        'GTM, GA4, Google Ads, Meta, Clarity, Hotjar, LinkedIn, TikTok y Bing: PixelYourSite, GTM4WP, Site Kit, MonsterInsights, Código personalizado de Elementor y cualquiera de esas etiquetas que quede en el HTML.',
    'Hide from search engines': 'Ocultar a los buscadores',
    'Last stopped emails': 'Últimos correos detenidos',
    'New posts do not notify update services or the sites they link to.': 'Las entradas nuevas no avisan a los servicios de actualización ni a las webs que enlazan.',
    'Not active on this site.': 'No está activo en esta web.',
    'Off': 'Desactivado',
    'Off by default, because many staging copies need to test forms and orders. Turn it on so customers never get emails from this copy.':
        'Desactivado por defecto, porque en muchos stagings hay que probar formularios y pedidos. Actívalo para que ningún cliente reciba correos de esta copia.',
    'Only on this domain: if the database is copied to another domain, it goes back to automatic.':
        'Solo en este dominio: si la base de datos se copia a otro dominio, vuelve a automático.',
    'Pause WooCommerce webhooks': 'Pausar los webhooks de WooCommerce',
    'Reason: %s.': 'Motivo: %s.',
    'Redirected': 'Redirigido',
    'STAGING': 'STAGING',
    'STAGING label in the toolbar': 'Etiqueta STAGING en la barra de herramientas',
    'Saved settings of other plugins are never changed: if this database is copied back to the live site, its analytics, indexing and email keep working as before.':
        'Nunca cambia los ajustes guardados de otros plugins: si esta base de datos vuelve a la web en producción, la medición, la indexación y el correo siguen como estaban.',
    'Send everything to this address instead (optional)': 'Enviarlo todo a esta dirección (opcional)',
    'Settings': 'Ajustes',
    'So nobody confuses this copy with the live site.': 'Para que nadie confunda esta copia con la web en producción.',
    'Staging Guard': 'Staging Guard',
    'Staging Guard is active but doing nothing:': 'Staging Guard está activado pero no hace nada:',
    'Staging Guard settings': 'Ajustes de Staging Guard',
    'Staging Guard is active: this copy is treated as staging.': 'Staging Guard está activo: esta copia se trata como staging.',
    'Stop outgoing email': 'Detener el correo saliente',
    'Stop pings and pingbacks': 'Detener pings y pingbacks',
    'Subject': 'Asunto',
    'Test orders are not sent to your ERP, CRM, shipping or invoicing service.': 'Los pedidos de prueba no llegan a tu ERP, CRM, servicio de envíos o de facturación.',
    'The <code>AGRSE_STAGING</code> constant (true or false) in wp-config.php overrides this choice.':
        'La constante <code>AGRSE_STAGING</code> (true o false) en wp-config.php manda sobre esta opción.',
    'To': 'Para',
    'WP_ENVIRONMENT_TYPE is not "production"': 'WP_ENVIRONMENT_TYPE no es «production»',
    'What to turn off': 'Qué desactivar',
    'When to act': 'Cuándo actuar',
    'noindex on every page (WordPress, Yoast, Rank Math, SEOPress) and an X-Robots-Tag header on every response.':
        'noindex en todas las páginas (WordPress, Yoast, Rank Math, SEOPress) y cabecera X-Robots-Tag en todas las respuestas.',
    'the AGRSE_STAGING constant in wp-config.php': 'la constante AGRSE_STAGING en wp-config.php',
    'the domain looks like a live site': 'el dominio parece de una web en producción',
    'the domain looks like a staging or development copy': 'el dominio parece de staging o desarrollo',
    'the mode is set to "Always on" for this domain': 'el modo está en «Siempre activo» para este dominio',
    'the mode is set to "Off"': 'el modo está en «Desactivado»',
}

src = (ROOT / 'agencia-rse-staging-guard.php').read_text(encoding='utf-8')
found = {m.replace("\\'", "'") for m in re.findall(
    r"(?:esc_html_e|esc_html__|esc_attr_e|esc_attr__|_e|__)\(\s*'((?:[^'\\]|\\.)*)'", src)}
missing = found - set(T)
if missing:
    raise SystemExit('Missing translations: %r' % sorted(missing))


def q(text):
    return '"' + text.replace('\\', '\\\\').replace('"', '\\"') + '"'


lines = [
    'msgid ""', 'msgstr ""',
    '"Project-Id-Version: Agencia RSE Staging Guard\\n"',
    '"Language: es_ES\\n"',
    '"MIME-Version: 1.0\\n"',
    '"Content-Type: text/plain; charset=UTF-8\\n"',
    '"Content-Transfer-Encoding: 8bit\\n"',
    '"Plural-Forms: nplurals=2; plural=n != 1;\\n"',
    '',
]
for msgid, msgstr in T.items():
    lines += ['msgid ' + q(msgid), 'msgstr ' + q(msgstr), '']

out = Path(__file__).resolve().parent / 'agencia-rse-staging-guard-es_ES-code.po'
out.write_text('\n'.join(lines), encoding='utf-8', newline='\n')
print('%d strings in the code, all translated -> %s' % (len(found), out.name))
