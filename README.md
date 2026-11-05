# เว็บรวมดีล Affiliate (Lazada / Shopee)

เว็บรวมดีลลดราคา พร้อมกราฟราคาย้อนหลัง ตารางเทียบราคาข้ามแพลตฟอร์ม และระบบนับคลิก
เขียนด้วย PHP + SQLite ไม่ต้องตั้งค่าฐานข้อมูล

## เริ่มใช้งาน (ครั้งแรก)

```bash
cd /Applications/MAMP/htdocs/cat-mltcenters
php bin/setup.php          # สร้าง config.php และตั้งรหัสผ่านหลังบ้าน
php bin/seed-demo.php      # (ไม่บังคับ) ใส่สินค้าตัวอย่างไว้ดูหน้าตาเว็บ
```

เปิดเว็บ:
- ผ่าน MAMP: เปิด MAMP แล้วเข้า http://localhost:8888/cat-mltcenters/
- ไม่ใช้ MAMP: `php -S localhost:8765 router.php` แล้วเข้า http://localhost:8765/

หลังบ้าน: `/admin/`

## เพิ่มสินค้า

1. สมัคร **Lazada Affiliate** และ **Shopee Affiliate** แล้วรอการอนุมัติ
2. เลือกสินค้า → สร้างลิงก์ Affiliate จากหน้า Affiliate ของแต่ละแพลตฟอร์ม
3. ใส่ลงเว็บได้ 2 วิธี
   - ทีละชิ้น: หลังบ้าน › **+ เพิ่มสินค้า**
   - ทีละหลายชิ้น: หลังบ้าน › **นำเข้า CSV** (ดาวน์โหลดไฟล์ตัวอย่างได้ในหน้านั้น)

**อัปเดตราคา:** นำเข้า CSV ไฟล์เดิมซ้ำ (แก้แค่คอลัมน์ราคา) ระบบจะจับคู่สินค้าจาก `product_url`
แล้วบันทึกราคาใหม่ลงกราฟให้อัตโนมัติ — ช่องที่เว้นว่างจะคงค่าเดิมไว้

**เทียบราคา:** ใส่ `group_key` เดียวกันให้สินค้าตัวเดียวกันที่ขายทั้งใน Lazada และ Shopee

ตั้ง cron ให้นำเข้าไฟล์ทุกวัน (ตัวอย่าง: ทุกวัน 08:00):

```
0 8 * * * php /path/to/cat-mltcenters/bin/import-csv.php /path/to/products.csv
```

## ก่อนเปิดเว็บจริง

- [ ] ลบสินค้าตัวอย่าง: `php bin/seed-demo.php --remove`
- [ ] ตั้งรหัสผ่านใหม่ของตัวเอง: `php bin/setup.php` แล้วลบ `data/admin-password.txt`
- [ ] แก้ `site_name`, `site_tagline`, `site_url` ใน `config.php`
- [ ] ใช้ HTTPS
- [ ] ส่ง `https://โดเมนของคุณ/sitemap.xml` ใน Google Search Console
- [ ] ตรวจเงื่อนไขของโปรแกรม Affiliate ทั้งสองแพลตฟอร์ม (การใช้รูปสินค้า, การเปิดเผยว่าเป็นลิงก์ Affiliate ฯลฯ)
- [ ] สำรองไฟล์ `data/app.sqlite` เป็นประจำ

> Hosting ต้องเป็น Apache ที่เปิด `mod_rewrite` และรองรับ `.htaccess` (PHP 7.2 ขึ้นไป, มี pdo_sqlite)

## Deploy ด้วย Dokploy (Docker)

- Build Type: **Dockerfile**, Docker File: `Dockerfile.prod`, Context: `.`
- Environment: `ADMIN_PASSWORD` (อย่างน้อย 10 ตัว), `SITE_URL` (เช่น `https://cat.mltcenters.com`) และเมื่อได้ API แล้ว `SHOPEE_APP_ID`, `SHOPEE_SECRET` (ไม่บังคับ: `THECATAPI_KEY`)
- Advanced › Volumes: mount ถาวรที่ `/var/www/html/data` ไม่งั้นฐานข้อมูลหายทุกครั้งที่ deploy
- Domains: ใส่โดเมน, Container Port `80`, เปิด HTTPS
- งานอัตโนมัติ (Schedules): `php bin/fetch-news.php` ทุกวัน และ `php bin/fetch-breeds.php` สัปดาห์ละครั้ง
- `config.php` ในคอนเทนเนอร์มาจาก `docker/config.php` (อ่านค่าจาก Environment) ไม่ต้องสร้างเอง

## ฟอนต์และรูปภาพ (ลิขสิทธิ์)

| สิ่งที่ใช้ | ที่มา | สัญญาอนุญาต |
|---|---|---|
| ฟอนต์หัวข้อ **Mali** | Google Fonts | SIL Open Font License 1.1 — ใช้เชิงพาณิชย์ได้ฟรี |
| ฟอนต์เนื้อหา **IBM Plex Sans Thai Looped** | Google Fonts | SIL Open Font License 1.1 — ใช้เชิงพาณิชย์ได้ฟรี |
| ภาพแมวเดิน/วิ่ง/นั่ง/ท่าเงา ([assets/cat-sprites.svg](assets/cat-sprites.svg)) | Freepik — "Flat design animation frames element collection", "Hand drawn cat animation frames element collection" และ "Set silhouette cat vector illustration" — ตัดพื้นหลัง แยกเฟรม และเปลี่ยนสีเป็นตัวแปร | Freepik License — บัญชีฟรี**ต้องให้เครดิต** (ใส่ไว้ท้ายเว็บแล้ว), Premium ไม่ต้อง; ห้ามใช้เป็นโลโก้ |
| ไอคอนคำสั่งแมว (assets/pet-cats.js) | [Lucide](https://lucide.dev) v0.454.0 (รวม crosshair = ล่าเหยื่อ, sunrise = บิดขี้เกียจ) + ไอคอน "หาว" และ "ไหมพรม" วาดเองในสไตล์เดียวกัน | ISC License — ใช้เชิงพาณิชย์ได้ เก็บข้อความลิขสิทธิ์ไว้ในโค้ด |
| เนื้อหาหน้าสายพันธุ์ | Wikipedia ภาษาไทย / Wikidata | CC BY-SA 4.0 (ต้องให้เครดิต + ใช้สัญญาเดียวกัน) / CC0 |
| ดาวในฉากหัวเว็บ | ["Cat, Sleeping Cat, Cartoon Cat" โดย VectorBy บน Pixabay](https://pixabay.com/vectors/cat-6940169/) | Pixabay Content License — ใช้เชิงพาณิชย์ได้ ห้ามใช้เป็นโลโก้ |
| โลโก้ favicon ไอคอนหมวด ลายอุ้งเท้า รูปแมวแทนรูปสินค้า | วาดใหม่เป็น SVG ในโปรเจกต์นี้ ([inc/illustrations.php](inc/illustrations.php)) | เป็นของเว็บนี้เอง |
| รูปสินค้า | ลิงก์รูปจาก Shopee/Lazada ที่มากับลิงก์ Affiliate | ใช้ตามเงื่อนไขโปรแกรม Affiliate ของแต่ละแพลตฟอร์ม |

ถ้าจะเพิ่มรูปปกบทความ ให้ใช้รูปที่ถ่ายเอง หรือจากเว็บรูปฟรีที่อนุญาตใช้เชิงพาณิชย์ (เช่น Unsplash, Pexels) และอ่านเงื่อนไขของแต่ละรูปก่อนใช้

## สายพันธุ์แมว + ข่าวแมว (อัตโนมัติ ไม่ต้องเขียนเอง)

- **หน้าสายพันธุ์** `/breeds`, `/breed/ชื่อ` — รายชื่อจาก Wikidata (CC0) เฉพาะสายพันธุ์ที่มีบทความภาษาไทย,
  เนื้อหาจาก Wikipedia ภาษาไทย (**CC BY-SA 4.0** — แต่ละหน้าแสดงที่มา ลิงก์บทความ และสัญญาอนุญาตแล้ว),
  ภาพแมวเป็นของเราเองเปลี่ยนสีตามสายพันธุ์ ไม่ใช้รูปถ่ายจากภายนอก
  ถ้าใส่ `thecatapi_key` (สมัครฟรีที่ thecatapi.com) ใน `config.php` จะได้แถบนิสัยเพิ่ม *(ยังไม่ได้ทดสอบกับ key จริง)*
- **ข่าวแมว** `/news` — เฉพาะ **หัวข้อ + ลิงก์ไปข่าวต้นทาง** จาก RSS ใน `config.php` (`news_feeds`)
  คัดเฉพาะหัวข้อที่มีคำใน `news_keywords` และตัดคำใน `news_exclude` (ข่าวหดหู่) — ไม่คัดลอกเนื้อข่าว
- อัปเดต: ปุ่มในหน้าภาพรวมหลังบ้าน หรือ
  ```
  php bin/fetch-breeds.php   # เดือนละครั้ง (~30 วินาที)
  php bin/fetch-news.php     # วันละ 2–3 ครั้ง
  ```
  cron ตัวอย่าง (บน hosting): `0 7,13,19 * * * php /path/bin/fetch-news.php` และ `0 4 1 * * php /path/bin/fetch-breeds.php`
- **บน Mac นี้ตั้งเวลาไว้แล้ว (launchd):** ข่าว 07:00 / 13:00 / 19:00 ทุกวัน, สายพันธุ์วันที่ 1 ของทุกเดือน 04:00
  — ไฟล์อยู่ที่ `deploy/com.deeltasmaew.fetch-news.plist` และ `deploy/com.deeltasmaew.fetch-breeds.plist`
  (ติดตั้งแล้วใน `~/Library/LaunchAgents/`), log อยู่ที่ `data/fetch-news.log` และ `data/fetch-breeds.log`
  ยกเลิก: `launchctl bootout gui/$(id -u)/com.deeltasmaew.fetch-news` (และ `...fetch-breeds`) แล้วลบไฟล์ใน `~/Library/LaunchAgents/`

## แมวเลี้ยงบนหน้าเว็บ + สร้างแมวของตัวเอง

[assets/pet-cats.js](assets/pet-cats.js) ใช้ภาพเฟรม [assets/cat-sprites.svg](assets/cat-sprites.svg)
(เดิน 3 เฟรม, วิ่ง 3 เฟรม, นั่ง/หาว/ง่วงมีฟองน้ำมูก 8 เฟรม, และท่าเงา 8 ท่า: กระโดดตะครุบ ยืดตัว เงยหน้ามอง ยืน ย่อง หมอบ เดิน ก้มดม — เติมตา จมูก ปากสีอ่อน และอก/ท้องสีอ่อนให้เข้ากับเฟรมอื่น) — โหลดไฟล์ครั้งเดียว และแต่ละตัวซ้อนเฟรมไว้ในตัว
สลับเฉพาะการแสดงผล จึงไม่กระพริบ; ขาก้าวตามระยะที่เดินจริง

แมวไปได้ทั่วจอ ไม่ตามเมาส์: เดินเล่น เดินเร็ว วิ่งพล่าน **วิ่งทะลุขอบจอแล้วโผล่อีกฝั่ง** นั่งมองรอบๆ หาว
นั่งหลับ (ฟองน้ำมูกพองยุบ) ตื่นแล้วบิดขี้เกียจ ลุกขึ้นเงยหน้ามองอย่างสงสัย ไล่จับกันเอง
และ**ล่าเหยื่อ** (ย่อง → หมอบส่ายก้น → กระโดดตะครุบเป็นโค้ง → ก้มดม)

- **กดที่ตัวแมว** → แมวหยุดนั่ง แล้วมีไอคอนวงกลมกางรอบตัว: เดินเล่น วิ่งพล่าน นั่งนิ่งๆ นอน หาว เล่นกัน
  วิ่งทะลุจอ มาตรงกลาง และ 🎨 แต่งตัวนั้น (ชี้ที่ไอคอนเพื่อดูชื่อคำสั่ง)
- **ปุ่ม 🐾 มุมซ้ายล่าง** → ไอคอนกางเป็นวงจากมุม: สั่งแมวทุกตัว, 🎨 แต่งแมว, ซ่อน/เรียกแมว
- **🎨 แต่งแมว** เปิดแผงด้านข้าง (ไม่ใช่ป๊อปอัป ใช้หน้าเว็บต่อได้): สร้างได้สูงสุด 6 ตัว — ตั้งชื่อ, เริ่มจาก 10 สายพันธุ์,
  ลาย (สีพื้น / ลายสลิด / ถุงเท้าขาว / แต้มสี / สามสี), สีขน, สีที่สอง, สีตา, สีปลอกคอ, ขนาด, นิสัย (ปกติ / ขี้เซา / ซนสุดๆ)
- เก็บใน localStorage ของเบราว์เซอร์แต่ละคน (ไม่ส่งไปเซิร์ฟเวอร์); ไม่แสดงสำหรับคนที่ตั้งค่าลดภาพเคลื่อนไหว

ลองใน Console: `petCats.command('dash')`, `petCats.command('play', 0)`, `petCats.reset()`

## โครงสร้าง

| ไฟล์/โฟลเดอร์ | หน้าที่ |
|---|---|
| `index.php` | หน้าเว็บทั้งหมด: หน้าแรก, `/c/หมวด`, `/p/สินค้า`, `/go/id` (นับคลิกแล้วส่งไปร้านค้า), `sitemap.xml`, `robots.txt` |
| `admin/` | หลังบ้าน: ภาพรวมคลิก, จัดการสินค้า, นำเข้า CSV |
| `inc/` | ฐานข้อมูล, ฟังก์ชันสินค้า, ตัวช่วยต่างๆ |
| `views/` | เทมเพลตหน้าเว็บ |
| `bin/` | คำสั่ง: setup, นำเข้า CSV, สินค้าตัวอย่าง |
| `data/` | ฐานข้อมูล SQLite (เว็บเข้าถึงไม่ได้) |

## ดึงสินค้าขายดีทุกเช้า (Shopee Affiliate API)

ดึงสินค้าขายดีที่สุด 15 อันดับจากคีย์เวิร์ดแต่ละหมวด แล้วแสดงในส่วน "🔥 ขายดีที่สุดวันนี้" บนหน้าแรก
สินค้าที่หลุดอันดับยังอยู่ในเว็บ แต่ถ้าไม่ถูกดึงซ้ำเกิน 14 วันจะถูกซ่อน เพื่อไม่ให้ราคาเก่าค้าง

1. ขอ App ID + Secret จาก Shopee Affiliate (เมนู Open API) แล้วใส่ใน `config.php` ส่วน `shopee_api`
2. ปรับหมวด/คีย์เวิร์ดและจำนวนใน `config.php` ส่วน `daily_top`
3. ลองรัน: `php bin/fetch-top-products.php` (หรือกด "ดึงตอนนี้" ในหน้าภาพรวมหลังบ้าน)
4. ตั้งเวลาทุกเช้า
   - บน Mac: ไฟล์ [deploy/com.deeltasmaew.fetch-top.plist](deploy/com.deeltasmaew.fetch-top.plist) (วิธีติดตั้งอยู่ในไฟล์)
   - บน hosting: `0 7 * * * php /path/to/cat-mltcenters/bin/fetch-top-products.php >> /path/to/cat-mltcenters/data/fetch.log 2>&1`

> Lazada ยังไม่รองรับการดึงอัตโนมัติ — ใช้ "นำเข้า CSV" ไปก่อน

## บทความ (ตัวดึงคนเข้าเว็บหลัก)

หลังบ้าน › **บทความ** — เขียนด้วย Markdown แบบง่าย และฝังสินค้าได้ (ราคาอัปเดตเอง):

| เขียนว่า | ผลลัพธ์ |
|---|---|
| `[product:12]` | การ์ดสินค้า ID 12 |
| `[products:ทรายแมว:4]` | ดีลเด่น 4 ชิ้นในหมวด |
| `[compare:tofu-litter-6l]` | ตารางเทียบราคา Lazada/Shopee |

ฉบับร่างดูตัวอย่างได้ผ่านลิงก์ลับในหลังบ้าน (Google ไม่เก็บ) แผนหัวข้อ 30 บทความอยู่ที่ [docs/content-plan.md](docs/content-plan.md)

## ขั้นต่อไปที่วางแผนไว้

- เชื่อม Lazada / Shopee Affiliate API เพื่อดึงสินค้าและราคาอัตโนมัติ (หลังได้รับอนุมัติ API)
- LINE OA แจ้งเตือนเมื่อสินค้าที่ติดตามลดราคา
