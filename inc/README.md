# Brut API: REST Endpoints Overview

Цей проєкт реалізує кастомний REST API для WordPress сайту Brutmaps.

---

## Аутентифікація

| Method | Route                      | Опис                                 |
|--------|----------------------------|--------------------------------------|
| POST   | `/login`                   | Авторизація через email / password   |
| POST   | `/google-login`           | Авторизація через Google email       |
| POST   | `/registration`            | Реєстрація користувача               |
| POST   | `/google-registration`     | Реєстрація через Google email        |
| POST   | `/check-email`             | Перевірка чи email зайнятий          |
| POST   | `/lost-password`           | Запит на скидання паролю             |
| POST   | `/reset-password`          | Скидання паролю через ключ           |
| POST   | `/change-password`         | Зміна паролю при авторизації         |
| POST   | `/token/refresh`           | Оновлення JWT токену                 |

---

## Профіль користувача

| Method | Route                  | Опис                                 |
|--------|------------------------|--------------------------------------|
| GET    | `/user-profile`        | Дані поточного користувача           |
| POST   | `/edit-profile`        | Оновити імʼя, email, фото            |
| GET    | `/user-countries`      | Список країн з WooCommerce           |
| DELETE | `/delete-account`      | Повне видалення облікового запису    |

---

## Обране

| Method | Route                   | Опис                                 |
|--------|-------------------------|--------------------------------------|
| GET    | `/favorites`            | Отримати список обраного             |
| POST   | `/favorites/toggle`     | Додати/видалити з обраного           |

---

## Блог

| Method | Route                        | Опис                           |
|--------|------------------------------|--------------------------------|
| GET    | `/posts?cat=["ukraine"]`     | Отримати пости по категоріях  |
| GET    | `/posts/:slug_or_id`         | Повний пост з банерами        |

---

## Продукти

| Method | Route          | Опис                         |
|--------|----------------|------------------------------|
| GET    | `/products`    | Список всіх продуктів        |

---

## Об'єкти (Sights)

| Method | Route                   | Опис                           |
|--------|-------------------------|--------------------------------|
| GET    | `/sights`               | GeoJSON колекція об'єктів     |
| GET    | `/sights/:slug_or_id`   | Повна інформація по об'єкту   |

---

## 📦 Структура


inc/
├── rest/
│   └── controllers/
│
├── services/
│
├── utils/ 
│
└── class-rest-route-registrar.php


---

## Вимоги

- WordPress 6.x
- WooCommerce (для `/products`)
- ACF Pro (для кастомних полів)
- JWT Auth (або власна реалізація)

---
