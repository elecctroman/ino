# Inovapin Woo Sync

Bu depo, Inovapin Woo Sync WordPress eklentisinin kaynak kodunu içerir. Eklentiyi dağıtmak için ZIP paketini kendiniz oluşturmanız gerekir.

## Paket Oluşturma

Yerel makinenizde aşağıdaki komutu çalıştırarak `inovapin-woo-sync.zip` arşivini oluşturabilirsiniz:

```bash
./bin/package.sh
```

Komut, depo kökünde `inovapin-woo-sync.zip` dosyasını üretir. Dosya `.gitignore` tarafından yok sayıldığı için sürüm kontrolüne dahil edilmez.

## Eklentiyi Kurma

1. Paket arşivini WordPress yönetim panelinizde **Eklentiler > Yeni Ekle > Eklenti Yükle** adımlarını izleyerek yükleyin.
2. Eklentiyi etkinleştirdikten sonra WooCommerce > Ayarlar > Entegrasyon sekmesindeki **Inovapin Woo Sync** sayfasından yapılandırmayı tamamlayın.

Detaylı kurulum ve kullanım yönergeleri için `inovapin-woo-sync/readme.txt` dosyasına bakın.
