=== Inovapin Woo Sync ===
Contributors: inovapin
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Profesyonel Inovapin entegrasyonu ile tedarikçi ürünlerini ve siparişlerini WooCommerce ile eşleştirin.

== Description ==
Inovapin Woo Sync, Inovapin v1 API'si ile tam uyumlu çalışarak ürün, kategori, stok, fiyat ve sipariş akışını otomatikleştirir. Çift yönlü senkronizasyon, özel gereksinim alanları ve ayrıntılı raporlama sunar.

== Installation ==
1. `inovapin-woo-sync.zip` dosyasını WordPress eklentileri alanına yükleyin ve etkinleştirin. (ZIP dosyası mevcut değilse depodaki `./bin/package.sh` komutu ile oluşturabilirsiniz.)
2. WooCommerce > Ayarlar > Entegrasyon sekmesinden **Inovapin Woo Sync** panelini açın.
3. API Base URL, e-posta, parola ve region kodunu girin.
4. "🪙 Token Al / Yenile" butonu ile erişim token'ını alın. API Key alanı gerekiyorsa otomatik doldurulur.
5. Senkron ayarlarını (stok, fiyat, görsel, kategori vb.) isteğinize göre düzenleyin ve kaydedin.
6. "🧪 Bağlantı Testi" ile bağlantıyı doğrulayın.
7. "🔄 Senkronu Başlat" butonu veya WP-CLI/REST komutları ile ürün ve kategorileri eşleştirin.

== Usage ==
* Ayarlar panelindeki kartlardan son senkron, son hata ve günlük güncellenen ürün sayısını takip edin.
* Otomatik görevler: ürünler saatlik, kategoriler günlük senkronize edilir. WP-Cron kapatıldıysa manuel tetikleyin.
* WP-CLI:
  * `wp inovapin test-connection`
  * `wp inovapin sync --categories --products`
  * `wp inovapin clear-cache`
* REST API:
  * `POST /wp-json/inovapin/v1/sync/run`
  * `GET /wp-json/inovapin/v1/health`
  * `POST /wp-json/inovapin/v1/callback/products`
* Sipariş oluştururken ürüne özel gereklilik alanları (ör. oyuncu ID) otomatik görüntülenir ve doğrulanır. Siparişler Inovapin API'ye `requireData` blokları ile gönderilmeye hazır metalar içerir.

== Database Schema ==
* `{prefix}inovapin_map` – tedarikçi/WooCommerce ürün eşlemesi
* `{prefix}inovapin_logs` – API ve senkron logları
* `{prefix}inovapin_stats` – günlük/haftalık/aylık performans verileri

== Cron ==
* `inovapin_sync_products` (saatlik)
* `inovapin_sync_categories` (günlük)

== Known Issues & Solutions ==
* **401 Yetkilendirme Hatası** – Token süresi dolduysa "🪙 Token Al / Yenile" butonu ile tekrar alın.
* **429 Rate Limit** – Sistem otomatik olarak exponential backoff uygular; birkaç dakika sonra tekrar deneyin.
* **Görsel indirilemiyor** – Kaynak URL geçersizse log kaydına bakın, gerekirse ürünü manuel güncelleyin.
* **Cron Çalışmıyor** – WP-Cron devre dışıysa sisteminizde gerçek cron job tanımlayın veya WP-CLI komutunu kullanın.

== Changelog ==
= 1.0.0 =
* İlk sürüm.
