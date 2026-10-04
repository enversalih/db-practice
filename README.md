# Relasyon Laboratuvarı

Türkçe ve gerçekçi bir hastane bilgi yönetim sistemi üzerinden PostgreSQL öğrenmek için hazırlanmış Laravel tabanlı eğitim ortamı.

## Hızlı başlangıç

```bash
./setup.sh
```

Tek gereksinim çalışan bir Docker kurulumudur. Script `.env` dosyasını oluşturur, güvenli bir Laravel uygulama anahtarı üretir, Docker image'larını hazırlar, migration'ları uygular ve küçük eğitim veri setini otomatik üretir. Uygulama hazır olduğunda:

- Uygulama: http://localhost:8090
- PostgreSQL: `localhost:54329`
- Veritabanı / kullanıcı / parola: `hospital_lab` / `hospital` / `hospital`

Durumu izlemek için:

```bash
docker compose ps
docker compose logs -f app
```

Script güvenle tekrar çalıştırılabilir; mevcut `.env` dosyası ve PostgreSQL verileri korunur.

## Veri profilleri

Varsayılan `small` profil hızlı başlangıç içindir. Üç profil de aynı ilişkisel yapıyı kullanır:

| Profil | Hasta | Randevu | Klinik/finans işlemi |
|---|---:|---:|---:|
| small | 500 | 1.800 | 350 |
| medium | 25.000 | 100.000 | 20.000 |
| full | 500.000 | 1.250.000 | 250.000 |

Tam veri setiyle sıfırdan başlatmak için mevcut hacmi kaldırıp profili seçin:

```bash
docker compose down -v
DATASET_PROFILE=full docker compose up -d
```

`full` üretimi bilgisayarın disk ve işlemci hızına göre uzun sürebilir. İlerleme `docker compose logs -f app` ile görülebilir. Aynı komut tekrar çalıştırıldığında veri üretici mevcut sayıları kontrol eder ve kayıtları çoğaltmaz.

## İçerik

- 70'in üzerinde ilişkili HBYS tablosu
- Hasta, doktor, randevu, ziyaret, teşhis, laboratuvar, reçete, fatura ve ödeme verileri
- Türkçe Faker verileri ve sabit seed ile tekrarlanabilir üretim
- Sayfalama ve arama destekli tablo veri önizlemesi
- PostgreSQL foreign key'lerinden otomatik üretilen etkileşimli ER diyagramı
- Gerçek veritabanında çalışan, salt-okunur transaction ve zaman aşımıyla korunan SQL eğitim alanı
- Sonuç kümesine göre doğrulanan başlangıç, orta ve ileri seviye alıştırmalar

## Yararlı komutlar

```bash
# Uygulama logları
docker compose logs -f app

# Sonradan daha büyük profile kadar veri üret
docker compose exec app php artisan hbys:seed medium

# Testleri çalıştır
docker compose exec app php artisan test
```
