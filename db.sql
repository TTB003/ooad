CREATE TABLE loai_tai_khoan (
    ma_loaitk INT PRIMARY KEY AUTO_INCREMENT,
    ten_loai ENUM('Admin','Sales','Kho','CSKH') NOT NULL
);

CREATE TABLE nhan_vien (
    ma_nhanvien INT PRIMARY KEY AUTO_INCREMENT,
    ho_ten VARCHAR(100) NOT NULL,
    chuc_vu VARCHAR(100),
    email VARCHAR(120) UNIQUE,
    sdt VARCHAR(20),
    mat_khau VARCHAR(255) NOT NULL,
    ma_donhang INT NULL,
    CONSTRAINT fk_nv_dh FOREIGN KEY (ma_donhang) REFERENCES don_hang(ma_donhang),
    ma_loaitk INT NOT NULL,
    CONSTRAINT fk_nv_loaitk FOREIGN KEY (ma_loaitk) REFERENCES loai_tai_khoan(ma_loaitk)
);

CREATE TABLE khach_hang (
    ma_khachhang INT PRIMARY KEY AUTO_INCREMENT,
    ho_ten VARCHAR(100) NOT NULL,
    hang_thanh_vien ENUM('Bronze','Silver','Gold','Platinum') DEFAULT 'Bronze',
    email VARCHAR(120),
    diachi VARCHAR(255),
    sdt VARCHAR(20),
    mat_khau VARCHAR(255) NOT NULL
);

CREATE TABLE xe (
    ma_xe INT PRIMARY KEY AUTO_INCREMENT,
    gia_ban DECIMAL(15,2) NOT NULL,
    hang_xe VARCHAR(100) NOT NULL,
    tinh_trang ENUM('Moi','Da_qua_su_dung','Dang_ban'),
    loai_xe VARCHAR(100),
    mau_xe VARCHAR(50),
    nam_san_xuat YEAR,
    ma_baotri INT,
    CONSTRAINT fk_xe_baotri FOREIGN KEY (ma_baotri) REFERENCES ton_kho(ma_baotri)  -- điều chỉnh nếu bảng khác
);

CREATE TABLE ton_kho (
    ma_baotrie INT PRIMARY KEY AUTO_INCREMENT,
    so_luong_ban INT,
    so_luong_nhap INT,
    so_luong_ton INT,
    ngay_cap_nhat DATE,
    ma_nhanvien INT NOT NULL,
    CONSTRAINT fk_tonkho_nv FOREIGN KEY (ma_nhanvien) REFERENCES nhan_vien(ma_nhanvien)
);

CREATE TABLE bao_hiem_xe (
    ma_baohiem INT PRIMARY KEY AUTO_INCREMENT,
    ngay_bat_dau DATE,
    phi_bao_hiem DECIMAL(12,2),
    ngay_mua DATE,
    ma_xe INT NOT NULL,
    ma_khachhang INT NOT NULL,
    CONSTRAINT fk_bh_xe FOREIGN KEY (ma_xe) REFERENCES xe(ma_xe),
    CONSTRAINT fk_bh_kh FOREIGN KEY (ma_khachhang) REFERENCES khach_hang(ma_khachhang)
);

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

CREATE TABLE bao_cao_thong_ke (
    ma_baocao INT PRIMARY KEY AUTO_INCREMENT,
    loai_baocao ENUM('Doanh_so','Ton_kho','Khach_hang'),
    ngay_tao DATE,
    noi_dung TEXT,
    ma_nhanvien INT NOT NULL,
    CONSTRAINT fk_bctk_nv FOREIGN KEY (ma_nhanvien) REFERENCES nhan_vien(ma_nhanvien)
);

CREATE TABLE bao_hanh_dich_vu (
    ma_baohanh INT PRIMARY KEY AUTO_INCREMENT,
    noi_dung TEXT,
    ngay_baohanh DATE,
    ngay_het_bh DATE,
    ma_khachhang INT NOT NULL,
    CONSTRAINT fk_bh_kh2 FOREIGN KEY (ma_khachhang) REFERENCES khach_hang(ma_khachhang)
);

CREATE TABLE don_hang (
    ma_donhang INT PRIMARY KEY AUTO_INCREMENT,
    ngay_lap DATE NOT NULL,
    tong_tien DECIMAL(15,2),
    trang_thai ENUM('Cho_duyet','Da_duyet','Da_thanh_toan','Huy'),
    ma_khuyenmai INT,
    ma_khachhang INT NOT NULL,
    CONSTRAINT fk_dh_khuyenmai FOREIGN KEY (ma_khuyenmai) REFERENCES khuyen_mai(ma_khuyenmai),
    CONSTRAINT fk_dh_kh FOREIGN KEY (ma_khachhang) REFERENCES khach_hang(ma_khachhang)
);

CREATE TABLE chi_tiet_don_hang (
    ma_xe INT NOT NULL,
    ma_donhang INT NOT NULL,
    don_gia DECIMAL(15,2) NOT NULL,
    so_luong INT NOT NULL,
    thanh_tien DECIMAL(15,2) GENERATED ALWAYS AS (don_gia * so_luong) STORED,
    PRIMARY KEY (ma_xe, ma_donhang),
    CONSTRAINT fk_ctdh_xe FOREIGN KEY (ma_xe) REFERENCES xe(ma_xe),
    CONSTRAINT fk_ctdh_dh FOREIGN KEY (ma_donhang) REFERENCES don_hang(ma_donhang)
);

CREATE TABLE thanh_toan (
    ma_thanhtoan INT PRIMARY KEY AUTO_INCREMENT,
    phuong_thuc ENUM('Tien_mat','Chuyen_khoan','The'),
    so_lien DECIMAL(15,2),
    trang_thai_tt ENUM('Cho','Thanh_cong','That_bai'),
    ngay DATE,
    ma_donhang INT NOT NULL,
    CONSTRAINT fk_tt_dh FOREIGN KEY (ma_donhang) REFERENCES don_hang(ma_donhang)
);

CREATE TABLE khuyen_mai (
    ma_khuyenmai INT PRIMARY KEY AUTO_INCREMENT,
    ten_khuyenmai VARCHAR(120) NOT NULL,
    muc_giam DECIMAL(5,2),
    ngay_batdau DATE,
    ngay_ketthuc DATE,
    dieu_kien TEXT,
    ma_donhang INT,
    CONSTRAINT fk_km_dh FOREIGN KEY (ma_donhang) REFERENCES don_hang(ma_donhang)
);