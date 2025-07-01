# Gunakan image PHP versi 8.1
FROM php:8.1-cli

# Salin semua file ke direktori kerja di container
COPY . /usr/src/myapp
WORKDIR /usr/src/myapp

# Jalankan built-in web server di port 10000 (wajib Render)
CMD ["php", "-S", "0.0.0.0:10000"]
