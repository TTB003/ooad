-- PostgreSQL schema for AutoLux car management system
-- Generated to match current application requirements (register/login/admin)

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- 1 ENUM definitions ------------------------------------------------------
CREATE TYPE loai_tk_enum AS ENUM ('Admin','NhanVien');
CREATE TYPE hang_tv_enum AS ENUM ('Bronze','Silver','Gold','Platinum');
CREATE TYPE tinh_trang_xe_enum AS ENUM ('Moi','Da_qua_su_dung','Dang_ban');
CREATE TYPE trang_thai_donhang_enum AS ENUM ('Cho_duyet','Da_duyet','Da_thanh_toan','Huy');
CREATE TYPE phuong_thuc_tt_enum AS ENUM ('Tien_mat','Chuyen_khoan','The');
CREATE TYPE trang_thai_tt_enum AS ENUM ('Cho','Thanh_cong','That_bai');
CREATE TYPE loai_baocao_enum AS ENUM ('Doanh_so','Ton_kho','Khach_hang');
CREATE TYPE trang_thai_taikhoan_enum AS ENUM ('active','disabled');
CREATE TYPE chuc_vu_enum AS ENUM (
    'Administrator',
    'Nhân viên bán hàng',
    'Nhân viên kho',
    'Nhân viên bảo hành/bảo dưỡng',
    'Nhân viên chăm sóc khách hàng'
);

-- 2 khuyen_mai -------------------------------------------------------------
CREATE TABLE khuyen_mai (
    ma_khuyenmai SERIAL PRIMARY KEY,
    ten_khuyenmai VARCHAR(120) NOT NULL,
    muc_giam NUMERIC(5,2),
    ngay_batdau DATE,
    ngay_ketthuc DATE,
    dieu_kien TEXT,
    ma_donhang INT
);

-- 3 don_hang ---------------------------------------------------------------
CREATE TABLE don_hang (
    ma_donhang SERIAL PRIMARY KEY,
    ngay_lap DATE NOT NULL,
    tong_tien NUMERIC(15,2),
    trang_thai trang_thai_donhang_enum,
    ma_khuyenmai INT,
    ma_khachhang INT NOT NULL,
    dia_chi_giao_hang TEXT,
    CONSTRAINT fk_dh_khuyenmai FOREIGN KEY (ma_khuyenmai) REFERENCES khuyen_mai(ma_khuyenmai)
);

-- 4 loai_tai_khoan ---------------------------------------------------------
CREATE TABLE loai_tai_khoan (
    ma_loaitk SERIAL PRIMARY KEY,
    ten_loai loai_tk_enum NOT NULL UNIQUE
);

-- 5 khach_hang -------------------------------------------------------------
CREATE TABLE khach_hang (
    ma_khachhang SERIAL PRIMARY KEY,
    ho_ten VARCHAR(100) NOT NULL,
    hang_thanh_vien hang_tv_enum DEFAULT 'Bronze',
    email VARCHAR(120) UNIQUE,
    diachi VARCHAR(255),
    sdt VARCHAR(20),
    mat_khau VARCHAR(255) NOT NULL
);

ALTER TABLE don_hang
    ADD CONSTRAINT fk_dh_kh FOREIGN KEY (ma_khachhang) REFERENCES khach_hang(ma_khachhang);

-- 6 nhan_vien --------------------------------------------------------------
CREATE TABLE nhan_vien (
    ma_nhanvien SERIAL PRIMARY KEY,
    ho_ten VARCHAR(100) NOT NULL,
    chuc_vu chuc_vu_enum,
    email VARCHAR(120) UNIQUE,
    sdt VARCHAR(20),
    mat_khau VARCHAR(255) NOT NULL,
    ma_donhang INT,
    ma_loaitk INT NOT NULL,
    CONSTRAINT fk_nv_dh FOREIGN KEY (ma_donhang) REFERENCES don_hang(ma_donhang),
    CONSTRAINT fk_nv_loaitk FOREIGN KEY (ma_loaitk) REFERENCES loai_tai_khoan(ma_loaitk)
);

-- 6b tai_khoan (tham chiếu khach_hang & nhan_vien) ---------------------------
CREATE TABLE tai_khoan (
    ma_taikhoan SERIAL PRIMARY KEY,
    username VARCHAR(120) NOT NULL UNIQUE,
    hash_matkhau VARCHAR(255) NOT NULL,
    trang_thai trang_thai_taikhoan_enum DEFAULT 'active',
    ma_loaitk INT NOT NULL REFERENCES loai_tai_khoan(ma_loaitk),
    ma_khachhang INT UNIQUE,
    ma_nhanvien INT UNIQUE,
    CONSTRAINT fk_tk_kh FOREIGN KEY (ma_khachhang) REFERENCES khach_hang(ma_khachhang) ON DELETE SET NULL,
    CONSTRAINT fk_tk_nv FOREIGN KEY (ma_nhanvien) REFERENCES nhan_vien(ma_nhanvien) ON DELETE SET NULL,
    CONSTRAINT chk_tk_owner CHECK (
        (ma_khachhang IS NOT NULL AND ma_nhanvien IS NULL)
        OR (ma_khachhang IS NULL AND ma_nhanvien IS NOT NULL)
    )
);

-- 7 ton_kho ----------------------------------------------------------------
CREATE TABLE ton_kho (
    ma_baotri SERIAL PRIMARY KEY,
    so_luong_ban INT,
    so_luong_nhap INT,
    so_luong_ton INT,
    ngay_cap_nhat DATE,
    ma_nhanvien INT NOT NULL,
    CONSTRAINT fk_tonkho_nv FOREIGN KEY (ma_nhanvien) REFERENCES nhan_vien(ma_nhanvien)
);

-- 8 xe ---------------------------------------------------------------------
CREATE TABLE xe (
    ma_xe SERIAL PRIMARY KEY,
    ma_hangxe INT NOT NULL,
    gia_ban NUMERIC(15,2) NOT NULL,
    hang_xe VARCHAR(100) NOT NULL,
    tinh_trang tinh_trang_xe_enum,
    loai_xe VARCHAR(100),
    mau_xe VARCHAR(50),
    nam_san_xuat INT,
    mo_ta TEXT,
    ma_baotri INT,
    CONSTRAINT fk_xe_baotri FOREIGN KEY (ma_baotri) REFERENCES ton_kho(ma_baotri),
    CONSTRAINT fk_xe_hangxe FOREIGN KEY (ma_hangxe) REFERENCES hang_xe(ma_hangxe)
);

-- 8b hinh_anh_xe -----------------------------------------------------------
CREATE TABLE hinh_anh_xe (
    ma_anh SERIAL PRIMARY KEY,
    ma_xe INT NOT NULL,
    url TEXT NOT NULL,
    
    la_chinh BOOLEAN DEFAULT FALSE,
    tao_luc TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hax_xe FOREIGN KEY (ma_xe) REFERENCES xe(ma_xe) ON DELETE CASCADE
);
CREATE UNIQUE INDEX uq_hinh_anh_xe_chinh ON hinh_anh_xe (ma_xe) WHERE la_chinh = TRUE;

-- 8c hang_xe ---------------------------------------------------------------
CREATE TABLE hang_xe (
    ma_hangxe SERIAL PRIMARY KEY,
    ten_hangxe VARCHAR(100) NOT NULL UNIQUE,
    
    logo_url TEXT,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_hang_xe_ten ON hang_xe(ten_hangxe);

-- 9 bao_hiem_xe ------------------------------------------------------------
CREATE TABLE bao_hiem_xe (
    ma_baohiem SERIAL PRIMARY KEY,
    ngay_bat_dau DATE,
    phi_bao_hiem NUMERIC(12,2),
    ngay_mua DATE,
    ma_xe INT NOT NULL,
    ma_khachhang INT NOT NULL,
    CONSTRAINT fk_bh_xe FOREIGN KEY (ma_xe) REFERENCES xe(ma_xe),
    CONSTRAINT fk_bh_kh FOREIGN KEY (ma_khachhang) REFERENCES khach_hang(ma_khachhang)
);

-- 10 tim_kiem ----------------------------------------------------------------
CREATE TABLE tim_kiem (
    ma_xe INT NOT NULL,
    ma_nhanvien INT NOT NULL,
    ma_tukho INT,
    mau_xe VARCHAR(50),
    ngay_tiemkiem DATE,
    ketqua_timduoc TEXT,
    PRIMARY KEY (ma_xe, ma_nhanvien),
    CONSTRAINT fk_tk_xe FOREIGN KEY (ma_xe) REFERENCES xe(ma_xe),
    CONSTRAINT fk_tk_nv FOREIGN KEY (ma_nhanvien) REFERENCES nhan_vien(ma_nhanvien)
);

-- 11 bao_cao_thong_ke ------------------------------------------------------
CREATE TABLE bao_cao_thong_ke (
    ma_baocao SERIAL PRIMARY KEY,
    loai_baocao loai_baocao_enum,
    ngay_tao DATE,
    noi_dung TEXT,
    ma_nhanvien INT NOT NULL,
    CONSTRAINT fk_bctk_nv FOREIGN KEY (ma_nhanvien) REFERENCES nhan_vien(ma_nhanvien)
);

-- 12 bao_hanh_dich_vu ------------------------------------------------------
CREATE TABLE bao_hanh_dich_vu (
    ma_baohanh SERIAL PRIMARY KEY,
    noi_dung TEXT,
    ngay_baohanh DATE,
    ngay_het_bh DATE,
    ma_khachhang INT NOT NULL,
    CONSTRAINT fk_bh_kh2 FOREIGN KEY (ma_khachhang) REFERENCES khach_hang(ma_khachhang)
);

-- 13 chi_tiet_don_hang -----------------------------------------------------
CREATE TABLE chi_tiet_don_hang (
    ma_xe INT NOT NULL,
    ma_donhang INT NOT NULL,
    don_gia NUMERIC(15,2) NOT NULL,
    so_luong INT NOT NULL,
    thanh_tien NUMERIC(15,2) GENERATED ALWAYS AS (don_gia * so_luong) STORED,
    PRIMARY KEY (ma_xe, ma_donhang),
    CONSTRAINT fk_ctdh_xe FOREIGN KEY (ma_xe) REFERENCES xe(ma_xe),
    CONSTRAINT fk_ctdh_dh FOREIGN KEY (ma_donhang) REFERENCES don_hang(ma_donhang)
);

-- 14 thanh_toan ------------------------------------------------------------
CREATE TABLE thanh_toan (
    ma_thanhtoan SERIAL PRIMARY KEY,
    phuong_thuc phuong_thuc_tt_enum,
    so_lien NUMERIC(15,2),
    trang_thai_tt trang_thai_tt_enum,
    ngay DATE,
    ma_donhang INT NOT NULL,
    CONSTRAINT fk_tt_dh FOREIGN KEY (ma_donhang) REFERENCES don_hang(ma_donhang)
);

-- 15 thong_bao -------------------------------------------------------------
CREATE TABLE thong_bao (
    ma_thongbao SERIAL PRIMARY KEY,
    tieu_de VARCHAR(255) NOT NULL,
    noi_dung TEXT,
    loai_thongbao VARCHAR(50), -- 'don_hang', 'kho', 'he_thong', 'khach_hang'
    ma_khachhang INT,
    ma_nhanvien INT,
    ma_donhang INT,
    da_doc BOOLEAN DEFAULT FALSE,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tb_kh FOREIGN KEY (ma_khachhang) REFERENCES khach_hang(ma_khachhang) ON DELETE CASCADE,
    CONSTRAINT fk_tb_nv FOREIGN KEY (ma_nhanvien) REFERENCES nhan_vien(ma_nhanvien) ON DELETE CASCADE,
    CONSTRAINT fk_tb_dh FOREIGN KEY (ma_donhang) REFERENCES don_hang(ma_donhang) ON DELETE CASCADE
);
CREATE INDEX idx_thong_bao_khachhang ON thong_bao(ma_khachhang, da_doc);
CREATE INDEX idx_thong_bao_nhanvien ON thong_bao(ma_nhanvien, da_doc);
CREATE INDEX idx_thong_bao_ngay_tao ON thong_bao(ngay_tao DESC);

-- 16 default roles & admin account -----------------------------------------
INSERT INTO loai_tai_khoan (ten_loai)
VALUES ('Admin'), ('NhanVien')
ON CONFLICT (ten_loai) DO NOTHING;

-- 16 Migration script (chạy nếu database đã tồn tại) ----------------------
-- Nếu bảng nhan_vien đã tồn tại với chuc_vu là VARCHAR, chạy script sau:

-- Bước 1: Tạo ENUM type (nếu chưa có)
DO $$ BEGIN
    CREATE TYPE chuc_vu_enum AS ENUM (
        'Administrator',
        'Nhân viên bán hàng',
        'Nhân viên kho',
        'Nhân viên bảo hành/bảo dưỡng',
        'Nhân viên chăm sóc khách hàng'
    );
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- Bước 2: Thêm cột tạm với ENUM type
ALTER TABLE nhan_vien ADD COLUMN chuc_vu_new chuc_vu_enum;

-- Bước 3: Chuyển đổi dữ liệu từ VARCHAR sang ENUM
UPDATE nhan_vien 
SET chuc_vu_new = CASE 
    WHEN chuc_vu = 'Administrator' THEN 'Administrator'::chuc_vu_enum
    WHEN chuc_vu = 'Nhân viên bán hàng' THEN 'Nhân viên bán hàng'::chuc_vu_enum
    WHEN chuc_vu = 'Nhân viên kho' THEN 'Nhân viên kho'::chuc_vu_enum
    WHEN chuc_vu = 'Nhân viên bảo hành/bảo dưỡng' THEN 'Nhân viên bảo hành/bảo dưỡng'::chuc_vu_enum
    WHEN chuc_vu = 'Nhân viên chăm sóc khách hàng' THEN 'Nhân viên chăm sóc khách hàng'::chuc_vu_enum
    ELSE NULL
END;

-- Bước 4: Xóa cột cũ và đổi tên cột mới
ALTER TABLE nhan_vien DROP COLUMN chuc_vu;
ALTER TABLE nhan_vien RENAME COLUMN chuc_vu_new TO chuc_vu;


WITH admin_role AS (
    SELECT ma_loaitk FROM loai_tai_khoan WHERE ten_loai = 'Admin' LIMIT 1
), upsert_admin AS (
    INSERT INTO nhan_vien (ho_ten, chuc_vu, email, sdt, mat_khau, ma_loaitk)
    SELECT
        'Quản trị hệ thống',
        'Administrator',
        'admin',
        '0000000000',
        crypt('Admin@123', gen_salt('bf')),
        ma_loaitk
    FROM admin_role
    ON CONFLICT (email) DO UPDATE
        SET mat_khau = EXCLUDED.mat_khau,
            ma_loaitk = EXCLUDED.ma_loaitk,
            chuc_vu = EXCLUDED.chuc_vu
    RETURNING ma_nhanvien, ma_loaitk
)
INSERT INTO tai_khoan (username, hash_matkhau, trang_thai, ma_loaitk, ma_nhanvien)
SELECT
    'admin',
    crypt('Admin@123', gen_salt('bf')),
    'active',
    ma_loaitk,
    ma_nhanvien
FROM upsert_admin
ON CONFLICT (username) DO UPDATE
    SET hash_matkhau = EXCLUDED.hash_matkhau,
        ma_nhanvien = EXCLUDED.ma_nhanvien,
        ma_loaitk = EXCLUDED.ma_loaitk,
        trang_thai = 'active';

-- Bổ sung cột ma_hangxe cho bảng xe nếu hệ thống đã tạo trước đó -------
DO $$
BEGIN
    ALTER TABLE xe ADD COLUMN IF NOT EXISTS ma_hangxe INT;
EXCEPTION
    WHEN duplicate_column THEN NULL;
END $$;

UPDATE xe x
SET ma_hangxe = hx.ma_hangxe
FROM hang_xe hx
WHERE x.ma_hangxe IS NULL
  AND hx.ten_hangxe = x.hang_xe;

DO $$
BEGIN
    ALTER TABLE xe
        ADD CONSTRAINT fk_xe_hangxe FOREIGN KEY (ma_hangxe) REFERENCES hang_xe(ma_hangxe);
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;