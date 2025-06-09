# Notes

1. Do not forget to replace the plugin-text-domain when creating new plugin inside phpcs.xml file

## Naprawiono: Problem z przekierowywaniem po płatności COD

### Problem

Zamówienia za pobraniem (COD) nie przekierowywały klientów z powrotem do aplikacji po potwierdzeniu zamówienia. Klienci pozostawali na stronie płatności WooCommerce zamiast być przekierowani na URL określony w `redirectUrl`.

### Rozwiązanie

1. **Włączono akcje przekierowań** - odkomentowano linie w `trunk/headless-wc.php`:

   - `add_action('plugins_loaded', 'headlesswc_check_plugin_requirements', 0);`
   - `add_action('template_redirect', 'headlesswc_redirect_after_order', 20);`

2. **Rozszerzono funkcję przekierowań** w `trunk/includes/redirect_after_order.php`:

   - Obsługa strony `order-received` (po udanej płatności)
   - Obsługa strony `order-pay` (dla COD i innych metod offline)
   - Automatyczne przekierowanie dla już potwierdzonych zamówień
   - JavaScript do automatycznego potwierdzania zamówień COD

3. **Dodano hook na zmianę statusu zamówienia**:

   - `woocommerce_order_status_changed` - przechowuje URL przekierowania gdy zamówienie COD zostanie potwierdzone
   - Używa WordPress transients do tymczasowego przechowywania URL przekierowania

4. **Dodano sprawdzanie oczekujących przekierowań**:
   - Funkcja `headlesswc_check_pending_redirect()` sprawdza czy jest oczekujące przekierowanie
   - Automatycznie przekierowuje przez JavaScript

### Jak to działa

1. **Standardowe płatności**: Klient przechodzi do `order-received` → automatyczne przekierowanie
2. **COD**:
   - Klient trafia na stronę `order-pay`
   - JavaScript automatycznie klika przycisk "Potwierdź zamówienie"
   - Po potwierdzeniu → przekierowanie do `redirectUrl`
   - Jeśli zamówienie już potwierdzone → natychmiastowe przekierowanie

### Testowanie

Aby przetestować:

1. Utwórz zamówienie z metodą płatności COD
2. Przejdź na URL zwrócony w `paymentUrl`
3. Zamówienie powinno zostać automatycznie potwierdzone
4. Klient powinien zostać przekierowany do URL określonego w `redirectUrl`
