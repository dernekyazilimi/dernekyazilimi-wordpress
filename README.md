# Dernek Yazılımı – WordPress eklentisi

[Dernek Yazılımı](https://github.com/lkdtr/dernekyazilimi) portalının bağış, gönüllü ve üyelik formlarını derneğin WordPress sitesine koyar. Formları sitenin kendi teması çizer; ödeme ve başvurunun devamı portalın sayfasında, site içindeki bir çerçevede sürer.

## Ne yapar

| Form | Sitede | Portalda (çerçeve) |
|---|---|---|
| Bağış | Tutar, bağış amacı, bağışçı bilgileri, ödeme yöntemi | Kartta ödeme kuruluşunun sayfası (sayfanın üstünde açılan pencerede), havalede hesap bilgileri ve referans kodu |
| Gönüllü kaydı | Ad, e-posta, SMS ile doğrulanan telefon, iletişim izinleri | – (parola, portalın gönderdiği e-postadaki bağlantıyla belirlenir) |
| Üyelik başvurusu | Hesabı açan ilk adım | Başvuru formunun tamamı |

- Formlar düz HTML'dir; yazı tipi, renk ve düğme biçimi temadan gelir.
- Çerçevedeki portal sayfaları menüsüz ve saydam açılır, yüksekliğini içeriğine göre ayarlar.
- Kart bilgisi yalnız ödeme kuruluşunun sayfasına girilir; WordPress'e ulaşmaz.
- Portalın API anahtarı tarayıcıya gitmez: formlar eklentinin REST uçlarına gönderir, eklenti cevapları sunucudan sunucuya portala iletir.

## Gereksinimler

- WordPress 6.2+, PHP 7.4+
- HTTPS üzerinden yayınlanan, güncel bir Dernek Yazılımı portalı

## Kurulum

1. Eklentiyi kurup etkinleştirin (zip'i **Eklentiler → Yeni ekle → Eklenti yükle** ile yükleyin).
2. Portalda **Yönetim paneli → Ayarlar → Kurum ayarları**:
   - "Gömülebilecek siteler" listesine WordPress sitenizin adresini yazın (sitenin kendi adresiyle aynı olmalı: `https://www.ornek.org.tr` ile `https://ornek.org.tr` ayrı adreslerdir).
   - "Web sitesi bağlantısı" kartından API anahtarı üretin. Anahtar yalnız bir kez gösterilir.
3. WordPress'te **Ayarlar → Dernek Yazılımı**: portal adresini ve anahtarı girin. "Bağlantı durumu" hangi formların açık olduğunu gösterir.
4. Bir sayfaya formun bloğunu (blok ekleyicide "Dernek Yazılımı" diye arayın) ya da kısa kodunu ekleyin.

Adres ve anahtar `wp-config.php` içinde de tanımlanabilir:

```php
define( 'DERNEKYAZILIMI_PORTAL_URL', 'https://portal.ornek.org.tr' );
define( 'DERNEKYAZILIMI_API_KEY', 'dy_...' );
```

## Kısa kodlar

| Kısa kod | Açıklama |
|---|---|
| `[dernekyazilimi_verify]` | Belge doğrulama; `?kod=...` içeren QR bağlantılarını otomatik sorgular |
| `[dernekyazilimi_donate]` | Bağış formu. İsteğe bağlı: `cause="3"` (seçili gelen bağış amacı), `amount="250"` |
| `[dernekyazilimi_volunteer]` | Gönüllü kayıt formu |
| `[dernekyazilimi_membership]` | Üyelik başvurusu |
| `[dernekyazilimi_agreement key="payment-terms"]` | Portalda yayınlanan sözleşme metni (ör. ödeme, iptal ve iade koşulları); `title="1"` başlığı da yazar |
| `[dernekyazilimi_payment_logos]` | Kart ödeme sağlayıcısının gösterilmesini istediği logolar (ör. alt bilgide) |

Bir form portalda kapalıysa ziyaretçi kısa bir bilgi görür. Portala ulaşılamıyorsa ya da modül kapalıysa ziyaretçi hiçbir şey görmez, yönetici ise formun yerinde nedenini görür.

## Belge doğrulama

1. WordPress’te “Belge doğrula” sayfası oluşturun; kalıcı bağlantısı `belge-dogrula` olsun.
2. Sayfaya **Belge doğrulama** bloğunu veya `[dernekyazilimi_verify]` kısa kodunu ekleyin.
3. Portalda **Yazışma ayarları → WordPress belge doğrulama sayfası** alanına bu sayfanın tam HTTPS adresini yazın.

Yeni asıl yazıların QR kodu ve doğrulama bağlantısı bu sayfayı açar. Daha önce hazırlanmış veya imzalanmış PDF’ler değişmez; ek belgelere QR eklenmez. Ziyaretçi kodu elle yazarsa yazının sayısını da girer ve sayı belgeyle eşleşmelidir; QR ile gelirse yalnız kodla sorgu otomatik yapılır. Sonuçta yeşil kalkan (iptal edilmişse kırmızı), yazı numarası, tarih ve konu gösterilir; PDF açılabilir, indirilebilir, sayfada önizlenir; diğer ekler ile imzalı özgün dosyalar ayrıca alınabilir. Portalda Cloudflare Turnstile açıksa bağış formundaki insan doğrulaması burada da çıkar; QR ile gelen sorgu doğrulama bitince otomatik başlar. Portalda Yazışmalar modülü ve eklentinin mevcut API bağlantısı etkin olmalıdır.

## Portala giden veri

Eklenti yalnız derneğin kendi portalına bağlanır. Hangi verinin ne zaman gönderildiği [readme.txt](readme.txt) içindeki "External services" bölümünde yazılıdır.

## Geliştirme

- Derleme adımı yok: düz PHP, JS ve CSS.
- Kaynak metinler İngilizcedir; Türkçe çeviri `languages/dernekyazilimi-tr_TR.po` dosyasındadır. Değişince derleyin:
  ```bash
  msgfmt -o languages/dernekyazilimi-tr_TR.mo languages/dernekyazilimi-tr_TR.po
  ```
- Portal tarafı: `/api/site/*` uçları ve gömme modu (portal reposunda `App\Support\SiteApi`, `App\Support\Embed`).
- Dağıtım paketi (`.gitattributes` pakete girmeyenleri ayıklar; paketteki klasör adı eklentinin slug'ı olmalıdır):
  ```bash
  git archive --format=zip --prefix=dernekyazilimi/ -o dernekyazilimi.zip HEAD
  ```
- WordPress.org'a göndermeden önce paketi [Plugin Check](https://wordpress.org/plugins/plugin-check/) ile denetleyin.

Değişiklikler fork üzerinden, `lkdtr/dernekyazilimi-wordpress` deposuna PR ile gelir.

## Lisans

GPL-2.0-or-later
