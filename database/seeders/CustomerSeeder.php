<?php

namespace Database\Seeders;

use App\Enums\CustomerType;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Pelanggan perorangan. `nik_region` berisi 12 digit wilayah
     * (provinsi, kabupaten, kecamatan, desa) sesuai NIK 16 digit.
     *
     * @var array<int, array<string, string>>
     */
    private const INDIVIDUALS = [
        [
            'name' => 'Budi Santoso', 'email' => 'budi.santoso@gmail.com', 'phone' => '0812 3456 7801',
            'address' => 'Jl. Cikutra Baru Raya No. 24, RT 04 / RW 07', 'city' => 'Bandung', 'province' => 'Jawa Barat',
            'postal_code' => '40124', 'nik_region' => '327301120805', 'birth' => '1994-08-12', 'gender' => 'male',
        ],
        [
            'name' => 'Siti Aminah', 'email' => 'siti.aminah@gmail.com', 'phone' => '0812 3456 7802',
            'address' => 'Jl. Tebet Barat Dalam No. 88, RT 06 / RW 03', 'city' => 'Jakarta Selatan', 'province' => 'DKI Jakarta',
            'postal_code' => '12530', 'nik_region' => '317101450382', 'birth' => '1982-03-14', 'gender' => 'female',
        ],
        [
            'name' => 'Agus Salim', 'email' => 'agus.salim@yahoo.com', 'phone' => '0812 3456 7803',
            'address' => 'Jl. Darmo Permai III No. 15, RT 02 / RW 09', 'city' => 'Surabaya', 'province' => 'Jawa Timur',
            'postal_code' => '60226', 'nik_region' => '357801170971', 'birth' => '1971-09-17', 'gender' => 'male',
        ],
        [
            'name' => 'Dewi Lestari', 'email' => 'dewi.lestari@gmail.com', 'phone' => '0812 3456 7804',
            'address' => 'Jl. Kaliurang KM 5 No. 3, RT 09 / RW 02', 'city' => 'Sleman', 'province' => 'DI Yogyakarta',
            'postal_code' => '55281', 'nik_region' => '340401520286', 'birth' => '1986-02-22', 'gender' => 'female',
        ],
        [
            'name' => 'Rizky Aditya', 'email' => 'rizky.aditya@gmail.com', 'phone' => '0812 3456 7805',
            'address' => 'Jl. Pandan Arum No. 42, RT 05 / RW 01', 'city' => 'Semarang', 'province' => 'Jawa Tengah',
            'postal_code' => '50134', 'nik_region' => '337401080490', 'birth' => '1990-04-08', 'gender' => 'male',
        ],
        [
            'name' => 'Maya Anggraini', 'email' => 'maya.anggraini@yahoo.com', 'phone' => '0812 3456 7806',
            'address' => 'Jl. Gatot Subroto No. 176, RT 11 / RW 05', 'city' => 'Medan', 'province' => 'Sumatera Utara',
            'postal_code' => '20112', 'nik_region' => '127301610793', 'birth' => '1993-07-11', 'gender' => 'female',
        ],
        [
            'name' => 'Fajar Nugroho', 'email' => 'fajar.nugroho@gmail.com', 'phone' => '0812 3456 7807',
            'address' => 'Jl. A.P. Pettarani No. 9, RT 03 / RW 04', 'city' => 'Makassar', 'province' => 'Sulawesi Selatan',
            'postal_code' => '90112', 'nik_region' => '737101140588', 'birth' => '1988-05-24', 'gender' => 'male',
        ],
        [
            'name' => 'Lestari Dewi', 'email' => 'lestari.dewi@gmail.com', 'phone' => '0812 3456 7808',
            'address' => 'Jl. By Pass Ngurah Rai No. 21, RT 08 / RW 02', 'city' => 'Badung', 'province' => 'Bali',
            'postal_code' => '80236', 'nik_region' => '510301290377', 'birth' => '1977-03-19', 'gender' => 'female',
        ],
        [
            'name' => 'Andi Setiawan', 'email' => 'andi.setiawan@gmail.com', 'phone' => '0812 3456 7809',
            'address' => 'Jl. Raden Fatah No. 55, RT 01 / RW 08', 'city' => 'Tangerang', 'province' => 'Banten',
            'postal_code' => '15111', 'nik_region' => '360101110265', 'birth' => '1965-02-01', 'gender' => 'male',
        ],
        [
            'name' => 'Ratna Sari Dewi', 'email' => 'ratna.sari.dewi@gmail.com', 'phone' => '0812 3456 7810',
            'address' => 'Jl. Margonda Raya No. 314, RT 07 / RW 06', 'city' => 'Depok', 'province' => 'Jawa Barat',
            'postal_code' => '16416', 'nik_region' => '327701490182', 'birth' => '1982-01-09', 'gender' => 'female',
        ],
        [
            'name' => 'Yoga Pratama', 'email' => 'yoga.pratama@yahoo.com', 'phone' => '0812 3456 7811',
            'address' => 'Jl. Ir. H. Juanda No. 120, RT 04 / RW 03', 'city' => 'Bogor', 'province' => 'Jawa Barat',
            'postal_code' => '16122', 'nik_region' => '360201060793', 'birth' => '1993-03-16', 'gender' => 'male',
        ],
        [
            'name' => 'Nabila Putri', 'email' => 'nabila.putri@gmail.com', 'phone' => '0812 3456 7812',
            'address' => 'Jl. Sisingamangaraja No. 68, RT 06 / RW 01', 'city' => 'Bandung', 'province' => 'Jawa Barat',
            'postal_code' => '40115', 'nik_region' => '327301701099', 'birth' => '1999-10-30', 'gender' => 'female',
        ],
        [
            'name' => 'Rudi Hartono', 'email' => 'rudi.hartono@gmail.com', 'phone' => '0812 3456 7813',
            'address' => 'Jl. Ahmad Yani No. 302, RT 10 / RW 04', 'city' => 'Semarang', 'province' => 'Jawa Tengah',
            'postal_code' => '50132', 'nik_region' => '337401180669', 'birth' => '1969-06-08', 'gender' => 'male',
        ],
        [
            'name' => 'Rahmawati Kusuma', 'email' => 'rahmawati.kusuma@yahoo.com', 'phone' => '0812 3456 7814',
            'address' => 'Jl. Teuku Umar No. 47, RT 02 / RW 06', 'city' => 'Banda Aceh', 'province' => 'Aceh',
            'postal_code' => '23111', 'nik_region' => '110101460291', 'birth' => '1991-02-06', 'gender' => 'female',
        ],
    ];

    /**
     * Pelanggan badan usaha. `npwp_base` berisi 14 digit badan hukum
     * (2 digit wilayah DJP + 12 digit nomor corporat) sebelum digit checksum.
     *
     * @var array<int, array<string, string>>
     */
    private const BUSINESSES = [
        [
            'company_name' => 'PT Nusantara Jaya Sentosa', 'name' => 'Siti Aminah', 'email' => 'purchasing@nusantarajaya.co.id',
            'phone' => '021-7804001', 'npwp_base' => '01005429430018', 'nik_region' => '317101450382', 'birth' => '1982-03-14', 'gender' => 'female',
            'address' => 'Menara Hijau Lt. 12, Jl. TB Simatupang Kav. 1', 'city' => 'Jakarta Selatan', 'province' => 'DKI Jakarta', 'postal_code' => '12510',
        ],
        [
            'company_name' => 'PT Sinar Mas Logistik Nusantara', 'name' => 'Budi Santoso', 'email' => 'finance@sinarmaslogistik.co.id',
            'phone' => '022-7201102', 'npwp_base' => '05288413740588', 'nik_region' => '327301120805', 'birth' => '1994-08-12', 'gender' => 'male',
            'address' => 'Jl. Soekarno Hatta No. 590, Gedung Metro Indah', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'postal_code' => '40286',
        ],
        [
            'company_name' => 'CV Karya Abadi Mandiri', 'name' => 'Agus Salim', 'email' => 'admin@karyaabadi.co.id',
            'phone' => '031-8433303', 'npwp_base' => '07229164077120', 'nik_region' => '357801170971', 'birth' => '1971-09-17', 'gender' => 'male',
            'address' => 'Ruko Manyar, Jl. Manyar Pogot No. 12', 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'postal_code' => '60176',
        ],
        [
            'company_name' => 'PT Wahana Selaras Teknologi', 'name' => 'Ratna Sari Dewi', 'email' => 'ga@wahanaselaras.co.id',
            'phone' => '0274-761204', 'npwp_base' => '05277341520860', 'nik_region' => '327701490182', 'birth' => '1982-01-09', 'gender' => 'female',
            'address' => 'Plaza Setia Lt. B1, Jl. Kaliurang KM 8 No. 100', 'city' => 'Sleman', 'province' => 'DI Yogyakarta', 'postal_code' => '55283',
        ],
        [
            'company_name' => 'PT Bintang Samudera Hukum', 'name' => 'Dewi Lestari', 'email' => 'hrd@bintangSamudera.co.id',
            'phone' => '024-7602205', 'npwp_base' => '06338550291430', 'nik_region' => '340401520286', 'birth' => '1986-02-22', 'gender' => 'female',
            'address' => 'Gedung Bintang Lt. 5, Jl. Pandan Arum No. 9', 'city' => 'Semarang', 'province' => 'Jawa Tengah', 'postal_code' => '50133',
        ],
        [
            'company_name' => 'PT Bumi Hijau Lestari', 'name' => 'Fajar Nugroho', 'email' => 'procurement@bumihijau.co.id',
            'phone' => '031-5566606', 'npwp_base' => '07349271665040', 'nik_region' => '737101140588', 'birth' => '1990-04-08', 'gender' => 'male',
            'address' => 'Jl. Raya Darmo No. 168-170', 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'postal_code' => '60226',
        ],
        [
            'company_name' => 'PT Samudera Biru Nusantara', 'name' => 'Yoga Pratama', 'email' => 'purchasing@samuderabiru.co.id',
            'phone' => '021-3987707', 'npwp_base' => '01468205310870', 'nik_region' => '360201060793', 'birth' => '1993-03-16', 'gender' => 'male',
            'address' => 'Menara Batavia Lt. 8, Jl. MH Thamrin No. 12', 'city' => 'Jakarta Pusat', 'province' => 'DKI Jakarta', 'postal_code' => '10310',
        ],
        [
            'company_name' => 'PT Tirta Kencana Kuliner', 'name' => 'Rahmawati Kusuma', 'email' => 'order@tirtakencana.co.id',
            'phone' => '022-7209908', 'npwp_base' => '05291063438110', 'nik_region' => '110101460291', 'birth' => '1991-02-06', 'gender' => 'female',
            'address' => 'Kav. A, Jl. Ir. H. Juanda No. 205', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'postal_code' => '40132',
        ],
    ];

    /**
     * Seed pelanggan perorangan dan badan usaha.
     */
    public function run(): void
    {
        foreach (self::INDIVIDUALS as $customer) {
            Customer::query()->updateOrCreate(
                ['email' => $customer['email']],
                [
                    'customer_type' => CustomerType::Individual->value,
                    'name' => $customer['name'],
                    'phone' => $customer['phone'],
                    'address' => $customer['address'],
                    'nik' => $this->nik($customer['nik_region'], $customer['birth'], $customer['gender']),
                    'npwp' => null,
                    'province' => null,
                    'city' => $customer['city'],
                    'company_name' => null,
                    'postal_code' => $customer['postal_code'],
                    'country' => 'Indonesia',
                    'notes' => null,
                    'is_active' => true,
                ]
            );
        }

        foreach (self::BUSINESSES as $customer) {
            Customer::query()->updateOrCreate(
                ['email' => $customer['email']],
                [
                    'customer_type' => CustomerType::Business->value,
                    'name' => $customer['name'],
                    'phone' => $customer['phone'],
                    'address' => $customer['address'],
                    'nik' => $this->nik($customer['nik_region'], $customer['birth'], $customer['gender']),
                    'npwp' => $this->npwp($customer['npwp_base']),
                    'province' => $customer['province'],
                    'city' => $customer['city'],
                    'company_name' => $customer['company_name'],
                    'postal_code' => $customer['postal_code'],
                    'country' => 'Indonesia',
                    'notes' => 'Pelanggan katering, faktur diterbitkan atas nama badan usaha.',
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * NIK 16 digit: 12 digit wilayah + tanggal lahir (DD, +40 untuk perempuan) + 2 digit tahun lahir.
     */
    private function nik(string $region, string $birth, string $gender): string
    {
        $born = CarbonImmutable::parse($birth);
        $day = $born->day + ($gender === 'female' ? 40 : 0);

        return $region.str_pad((string) $day, 2, '0', STR_PAD_LEFT).$born->format('y');
    }

    /**
     * NPWP 15 digit: 14 digit badan hukum + checksum, ditulis sebagai 2.3.3.1.3.3.
     */
    private function npwp(string $base): string
    {
        $weights = [2, 1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1, 2, 1];

        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += ((int) $base[$index]) * $weight;
        }

        $checkDigit = $sum % 11;
        $digits = $base.($checkDigit === 10 ? 0 : $checkDigit);

        return implode('.', [
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 1),
            substr($digits, 9, 3),
            substr($digits, 12, 3),
        ]);
    }
}
