<!DOCTYPE html>
<html lang="fa">
<link rel="stylesheet" type="text/css" href="<?php echo get_stylesheet_uri(); ?>"/>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<title>گروه معماری زیگورات</title>
    <style>
        body {
			font-family: Tanha !important;
            line-height: 1.6;
            background-color: #f8f8f8;
            margin: 0;
            padding: 0;
            direction: rtl; /* اضافه کردن ویژگی راست به چپ */
            text-align: right; /* راست‌چین کردن متن */
        }
        .header {
            background-color: #3B3B3B;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .contact {
            display: grid;
            grid-gap: 5px;
            grid-template-columns: 1fr 1fr 1fr;
            align-items: center; /* تراز کردن محتوا به صورت عمودی در مرکز */
            width: 600px; /* عرض کارت */
            height: 200px; /* ارتفاع کارت */
            margin: 20px auto; /* مرکز قرار دادن کارت */
            padding: 20px; /* فاصله داخلی */
            background-color: #D7A843; /* پس‌زمینه سفید */
            border-radius: 15px; /* لبه‌های گرد */
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2); /* سایه کارت */
        }
        .contact .name{
            font-size: 28px; /* اندازه فونت عنوان */
            font-weight: bold; /* ضخیم کردن عنوان */
            margin: 0; /* حذف فاصله بالا و پایین عنوان */
        }
        .contact .logo {
            flex: 0 0 30%; /* عرض ثابت برای لوگو */
            text-align: center; /* متن و محتوا در وسط لوگو */
        }

        .contact .logo img {
            width: 90%; /* عرض لوگو */
            height: auto; /* ارتفاع لوگو */
            border-radius: 50%; /* لوگو دایره‌ای باشد */
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.5); /* سایه زیبا برای لوگو */
        }

        .contact .instagram {
            flex: 1; /* بخش وسط فضای بیشتری بگیرد */
            text-align: center; /* متن در وسط قرار گیرد */
        }

        .contact .instagram a {
            display: block;
            align-items: center;
            font-size: 16px;
            text-decoration: none;
            margin: 10px 0; /* فاصله بین لینک‌ها */
            transition: color 0.3s;
            color: #333;
        }

        .contact .instagram a i {
            margin-left: 10px; /* فاصله بین آیکون و متن */
            font-size: 20px; /* اندازه آیکون */
            color: #333; /* رنگ آیکون */
        }

        .contact .instagram a:hover {
            color: #0056b3; /* تغییر رنگ لینک هنگام هاور */
        }

        .contact .instagram a:hover i {
            color: #0056b3; /* تغییر رنگ آیکون هنگام هاور */
        }

        .contact .phone {
            flex: 0 0 20%; /* عرض ثابت برای شماره تلفن */
            text-align: center; /* متن در وسط قرار گیرد */
            font-size: 16px; /* اندازه فونت شماره‌ها */
        }

        .contact .phone a {
            color: #333; /* رنگ شماره تلفن */
            text-decoration: none; /* حذف خط زیر شماره */
            font-weight: bold; /* ضخیم کردن شماره */
            display: block;
        }

        @media (max-width: 980px) {
            .contact {
                display: flex;
                flex-direction: column; /* چینش عمودی عناصر */
                align-items: center; /* مرکز چین کردن آیتم‌ها */
                width: 50%; /* پهنای کامل برای موبایل */
                height: auto; /* ارتفاع خودکار برای تنظیم بهتر */
            }
            .contact .logo, 
            .contact .phone, 
            .contact .instagram {
                flex: unset; /* لغو تنظیمات flex قبلی */
                margin: 20px; /* فاصله بین بخش‌ها */
                width: 100%; /* پهنای کامل هر بخش */
                text-align: center; /* تراز متن به مرکز */
            }

            .contact .logo img {
                max-width: 80%; /* محدود کردن اندازه لوگو */
                height: auto;
            }
        }


        .work {
            margin: 20px 0;
        }
        .work-item {
            margin: 10px 0;
            padding: 10px;
            background-color: #e2e2e2;
            text-align: center;
            border-radius: 5px;
        }
        .images {
            display: flex;
            flex-wrap: wrap;
            gap: 10px; /* فاصله بین تصاویر */
            justify-content: center; /* وسط‌چین کردن تصاویر */
        }

        .images img {
            max-width: 200px; /* عرض حداکثر برای هر تصویر */
            height: auto;
            border-radius: 5px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }
        @media (max-width: 980px) {
            .images img {
                max-width: 90%;
            }
        } 
        .footer {
            text-align: center;
            padding: 20px;
            background-color: #333;
            color: #fff;
        }
		.download-container {
    text-align: center;
    margin-top: 20px;
}

.download-btn {
    background-color: #3B3B3B;
    color: white;
    padding: 10px 20px;
    font-size: 18px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.3s;
	margin: 20px;
}

.download-btn:hover {
    background-color: #555555;
}
        .slider {
    position: relative;
    max-width: 600px;
    margin: auto;
}

.slide {
    display: none;
    text-align: center;
}

img {
    width: 100%;
    border-radius: 5px;
}

.prev, .next {
    cursor: pointer;
    position: absolute;
    top: 50%;
    width: auto;
    padding: 10px;
    background-color: rgba(0,0,0,0.5);
    color: white;
    font-size: 20px;
    border: none;
    z-index: 10;
}

.next {
    right: 0;
}
.prev {
    left: 0;
}
    </style>
</head>
<body>
<div class="header">
    <h1>گروه معماری زیگورات</h1>
    <h1>دکوراسیون - بازسازی - تابلو و نمای ساختمان - غرفه نمایشگاهی</h1>
    <h1>از طراحی تا اجرا</h1>
    <h1>نماینده انحصاری پنل فایبرسمنت</h1>
</div>

<div class="container">
    <div class="contact">
        
        
        <div class="phone">
            <p class="name">موثق</p>
            <a href="tel:09125606941">0912-560-6941</a>
            <a href="tel:09125672730">0912-567-2730</a>
            <p class="name">فلاحی</p>
            <a href="tel:09123601784">0912-360-1784</a>
        </div>
        <div class="instagram">
            <a href="https://www.instagram.com/ziggurat_arch" target="_blank">
            <i class="fab fa-instagram"></i> Ziggurat_arch
            </a>
            <a href="https://www.instagram.com/ziggurat.arch" target="_blank">
                <i class="fab fa-instagram"></i> Ziggurat.arch
            </a>
            <a href="https://twitter.com/zigguratcorp" target="_blank">
                <i class="fab fa-twitter"></i> ZigguratCorp
            </a>
            <a href="https://www.youtube.com/zigguratcorp" target="_blank">
                <i class="fab fa-youtube"></i> ZigguratCorp
            </a>
        </div>
        <div class="logo">
            <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/03/123.png" alt="Company Logo">
        </div>
    </div>
<div class="download-container">
    <a href="http://zigurat.eleknow.com/wp-content/uploads/2025/05/Catalog_A4_low.pdf" download>
        <button class="download-btn">📄 دانلود کاتالوگ آ4</button>
    </a>
</div>
	<div class="download-container">
    <a href="http://zigurat.eleknow.com/wp-content/uploads/2025/05/Catalog_A3_Ersali_low.pdf" download>
        <button class="download-btn">📄 دانلود کاتالوگ آ3</button>
    </a>
</div>

    <div class="slider">
    <div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-1-scaled.jpg" alt="Ziggurat01">
    </div>
		<div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-2-scaled.jpg" alt="Ziggurat02">
    </div>
	<div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-3-scaled.jpg" alt="Ziggurat03">
    </div>
	<div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-4-scaled.jpg" alt="Ziggurat04">
    </div>
	<div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-5-scaled.jpg" alt="Ziggurat05">
    </div>
	<div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-6-scaled.jpg" alt="Ziggurat06">
    </div>
	<div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-7-scaled.jpg" alt="Ziggurat07">
    </div>
	<div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-8-scaled.jpg" alt="Ziggurat08">
    </div>
	<div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-9-scaled.jpg" alt="Ziggurat09">
    </div>
	<div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-10-scaled.jpg" alt="Ziggurat10">
    </div>
	<div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-11-scaled.jpg" alt="Ziggurat11">
    </div>
	<div class="slide fade">
        <img src="http://zigurat.eleknow.com/wp-content/uploads/2025/04/Catalog-12-scaled.jpg" alt="Ziggurat12">
    </div>
    <button class="prev" onclick="changeSlide(-1)">❯</button>
    <button class="next" onclick="changeSlide(1)">❮</button>
</div>

<script>
let slideIndex = 0;
showSlides();

function changeSlide(n) {
    showSlides(slideIndex += n);
}

function showSlides() {
    let slides = document.getElementsByClassName("slide");
    for (let i = 0; i < slides.length; i++) {
        slides[i].style.display = "none";
    }
    slideIndex = (slideIndex + slides.length) % slides.length;
    slides[slideIndex].style.display = "block";
}

setInterval(() => { changeSlide(1); }, 5000); // تغییر تصویر هر 5 ثانیه
</script>
</div>

<div class="footer">
    <p>© 2025 تمامی حقوق محفوظ است.</p>
</div>

</body>
</html>
