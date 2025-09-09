# HeadlessWC: Ultimate eCommerce Decoupler

HeadlessWC transforms your eCommerce approach by providing custom eCommerce endpoints for a headless checkout experience. This revolutionary plugin is designed to cater to the evolving needs of online stores that seek agility, speed, and an enhanced user experience without the constraints of traditional eCommerce platforms.

[WordPress Plugin Site](https://wordpress.org/plugins/headless-wc/)

[NPM Client Package](https://www.npmjs.com/package/headless-wc-client)

[API Documentation](https://dawidw5219.github.io/headless-wc/)

## Internationalization (i18n)

The plugin is fully localized with Text Domain `headless-wc`. English (en_US) strings are the source. Polish (pl_PL) is provided.

### Where strings live

- PHP: wrap all user-facing strings with translation helpers: `__( 'Text', 'headless-wc')`, `_e`, `_x`, `sprintf( __( 'Text %s', 'headless-wc'), $var)`.
- Admin labels and descriptions already use the correct domain.

### Files

- POT template: `trunk/languages/headless-wc.pot`
- Polish PO: `trunk/languages/headless-wc-pl_PL.po`
- Polish MO (compiled): `trunk/languages/headless-wc-pl_PL.mo`

### Generate/Update POT and compile PO→MO

```bash
pnpm i18n
```

This runs:

- `pnpm i18n:pot` → updates `headless-wc.pot` via grunt-wp-i18n
- `pnpm i18n:compile` → compiles `pl_PL.po` to `pl_PL.mo`

Requirements: `msgfmt` (gettext). On macOS: `brew install gettext && brew link --force gettext`.

### Adding new strings

1. Wrap new strings in code with the correct domain `headless-wc`.
2. Regenerate POT: `pnpm i18n:pot`.
3. Update `headless-wc-pl_PL.po` using your PO editor (Poedit) or manually.
4. Compile: `pnpm i18n:compile`.
5. Ensure Site Language is set to Polish to see translations, or keep English by default.

### Notes

- Keep error codes (e.g., `USER_EXISTS`) stable and untranslated; only messages are localized.
- Avoid string concatenations in translatable text; use `sprintf`.
