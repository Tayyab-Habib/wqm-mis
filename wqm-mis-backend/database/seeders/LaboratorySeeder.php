<?php

namespace Database\Seeders;

use App\Enums\GenderEnum;
use App\Models\Designation;
use App\Models\District;
use App\Models\Division;
use App\Models\Laboratories\Laboratory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LaboratorySeeder extends Seeder
{
    /**
     * Run the database seeds
     *
     * @return void
     */
    public function run()
    {
        $laboratories = [
            [
                'name' => 'Central Laboratory Peshawar',
                'latitude' => '34.0043',
                'longitude' => '71.5448',
                'focal_person_id',
                'logo' => 'images/default.jpg',
                'district_id' => 'Peshawar',
                'division_id' => 'Peshawar',
                'province_id' => 1,
                'address' => 'PHED H/Q, Plot # 40, Sector B-2, Phase-5, Hayatabad Peshawar',
                'phone' => '0919217788',
                'fax' => '091 9217788',
                'email' => 'srophed@gmail.com',
                'users' => [
                    [
                        'name' => 'Sahibzada Muhammad Adeel ',
                        'gender' => 'Male',
                        'basic_pay_scale' => '17',
                        'date_of_birth' => '1985-04-29',
                        'designation' => 'Research Officer',
                        'district_id' => 1,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Provincial Technical In-charge of the PHED Labs',
                        'assigned_parameters' => 'Special Tests',
                        'career_background' => 'Teaching',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'M-S Chemistry',
                        'email' => 'adeelchemistry@gmail.com',
                        'phone_number' => '03339656580'
                    ],
                    [
                        'name' => 'Haseena Nazneen',
                        'gender' => 'Female',
                        'basic_pay_scale' => '16',
                        'date_of_birth' => '1994-04-02',
                        'designation' => 'Assistant Research Officer',
                        'district_id' => 1,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Analysis In-charge at PHE Water Quality Central Laboratory Peshawar',
                        'assigned_parameters' => 'Physical, Chemical, Microbial and Heavy Metals Analysis',
                        'career_background' => 'Researcher, Research Associate',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'M-Phil Microbiology',
                        'email' => 'Haseenanazneen135@gmail.com',
                        'phone_number' => '03005907135'
                    ],
                    [
                        'name' => 'Zeshan Khan',
                        'gender' => 'Male',
                        'basic_pay_scale' => '11',
                        'date_of_birth' => '1989-07-01',
                        'designation' => 'Laboratory Attendant',
                        'district_id' => 1,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Lab Attendant',
                        'assigned_parameters' => 'Nil',
                        'career_background' => 'Nil',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'Middle Pass',
                        'email' => fake()->email,
                        'phone_number' => '03414322361'
                    ],
                    [
                        'name' => 'Shah Saoud',
                        'gender' => 'Male',
                        'basic_pay_scale' => '6',
                        'date_of_birth' => '1982-04-01',
                        'designation' => 'Laboratory Attendant',
                        'district_id' => 1,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Lab Attendant',
                        'assigned_parameters' => 'Nil',
                        'career_background' => 'Nil',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'Middle Pass',
                        'email' => fake()->email,
                        'phone_number' => '03414322361'
                    ],
                    [
                        'name' => 'Irfanullah',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1982-04-01',
                        'designation' => 'Driver',
                        'district_id' => 1,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Lab Attendant',
                        'assigned_parameters' => 'Nil',
                        'career_background' => 'Nil',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'Middle Pass',
                        'email' => fake()->email,
                        'phone_number' => '03414322361'
                    ],
                    [
                        'name' => 'Irfan Khan',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1982-04-01',
                        'designation' => 'Naib Qasid',
                        'district_id' => 1,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Lab Attendant',
                        'assigned_parameters' => 'Nil',
                        'career_background' => 'Nil',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'Middle Pass',
                        'email' => fake()->email,
                        'phone_number' => '03414322361',
                    ],
                    [
                        'name' => 'Amanullah',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1982-04-01',
                        'designation' => 'Sweepr',
                        'district_id' => 1,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Lab Attendant',
                        'assigned_parameters' => 'Nil',
                        'career_background' => 'Nil',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'Middle Pass',
                        'email' => fake()->email,
                        'phone_number' => '03414322361',
                    ],
                    [
                        'name' => 'Vacant',
                        'gender' => 'male',
                        'date_of_birth' => '1982-04-01',
                        'employee_status' => 'Permanent',
                        'basic_pay_scale' => '11',
                        'district_id' => 1,
                        'present_duty' => 'Lab Attendant',
                        'designation' => 'Lab Attendant',
                        'assigned_parameters' => 'Nil',
                        'career_background' => 'Nil',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'Middle Pass',
                        'email' => fake()->email,
                        'phone_number' => '03149088663',
                    ],
                ],
            ],
            [
                'name' => 'Swat Laboratory',
                'latitude' => '35.3005',
                'longitude' => '72.4823',
                'focal_person_id',
                'logo' => 'images/default.jpg',
                'district_id' => 'Swat',
                'division_id' => 'Malakand',
                'province_id' => 1,
                'address' => 'Near O/O XEN PHE Division Swat Saidu Sharif, Swat',
                'phone' => '09469240163',
                'fax' => '09469240163',
                'email' => 'aroswat@gmail.com',
                'users' => [
                    [
                        'name' => 'Hazrat Wahab',
                        'gender' => 'Male',
                        'basic_pay_scale' => '16',
                        'date_of_birth' => '1987-07-22',
                        'designation' => 'Assistant Research Officer',
                        'district_id' => 12,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'In-charge Regional Water Quality Testing Laboratory at PHE Division Swat',
                        'assigned_parameters' => 'Physical,Chemical and Microbial',
                        'career_background' => 'Lecturer',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'M-Sc Microbiology',
                        'email' => 'hwahabkhan@gmail.com',
                        'phone_number' => '03122826264',
                    ],
                    [
                        'name' => 'Aftab Alam',
                        'gender' => 'Male',
                        'basic_pay_scale' => '8',
                        'date_of_birth' => '1990-04-05',
                        'designation' => 'Laboratory Assistant',
                        'district_id' => 12,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory Assistant',
                        'assigned_parameters' => 'Physical and Microbial',
                        'career_background' => 'Laboratory Technician ',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'B-Sc Chemistry,2 years Pathology Diploma',
                        'email' => 'aftab3196@gmail.com',
                        'phone_number' => '03329431966'
                    ],
                    [
                        'name' => 'Israr Ahmad',
                        'gender' => 'Male',
                        'basic_pay_scale' => '8',
                        'date_of_birth' => '1995-03-05',
                        'designation' => 'Laboratory Assistant',
                        'district_id' => 12,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory Assistant',
                        'assigned_parameters' => 'Chemical',
                        'career_background' => 'Laboratory Technician ',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'B-A,2 years Pathology Diploma',
                        'email' => 'israrlab@gmail.com',
                        'phone_number' => '03469415839'
                    ],
                    [
                        'name' => 'Amir Khan',
                        'gender' => 'Male',
                        'basic_pay_scale' => '6',
                        'date_of_birth' => '1993-03-10',
                        'designation' => 'Laboratory Attendant',
                        'district_id' => 12,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory Attendant',
                        'assigned_parameters' => 'Physical',
                        'career_background' => 'Nill',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'B-A , DAE (civil)',
                        'email' => 'amirphedswat@gmail.com',
                        'phone_number' => '03480151671'
                    ],
                    [
                        'name' => 'Atta Ullah Khan',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1975-01-05',
                        'designation' => 'Laboratory Attendant',
                        'district_id' => 12,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory Attendant',
                        'assigned_parameters' => 'Physical',
                        'career_background' => 'Nill',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'Matriculate',
                        'email' => 'Nil',
                        'phone_number' => '-'
                    ],
                ],
            ],
            [
                'name' => 'Timergara (at Batkhela) Laboratory',
                'latitude' => '34.8095',
                'longitude' => '71.9749',
                'focal_person_id',
                'logo' => 'images/default.jpg',
                'district_id' => 'Lower Dir',
                'division_id' => 'Malakand',
                'province_id' => 1,
                'address' => '',
                'phone' => '0932413028',
                'fax' => '0932413028',
                'email' => 'arobla@gmail.com',
                'users' => [
                    [
                        'name' => 'Shah Nawaz',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1994-11-20',
                        'designation' => 'Laboratory Assistant',
                        'district_id' => 11,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory Assistant',
                        'assigned_parameters' => 'Physical,Chemical and Microbial',
                        'career_background' => 'Laboratory Supervisor  ',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'FSC,2 years Pathology Diploma,BS in progress  ',
                        'email' => 'sn95341@gmail.com',
                        'phone_number' => '03408362118'
                    ],
                    [
                        'name' => 'Muhammad Ismail',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1994-01-19',
                        'designation' => 'Laboratory Attendant',
                        'district_id' => 11,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory  Attendant',
                        'assigned_parameters' => 'Physical and Microbial',
                        'career_background' => 'Nil ',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'FA,Pathology Diploma in progress  ',
                        'email' => fake()->email,
                        'phone_number' => '03349346435'
                    ],
                ],
            ],
            [
                'name' => 'Kohat Laboratory',
                'latitude' => '33.5868',
                'longitude' => '71.4416',
                'focal_person_id',
                'logo' => 'images/default.jpg',
                'district_id' => 'Kohat',
                'division_id' => 'Kohat',
                'province_id' => 1,
                'address' => 'Near O/O the SE Kohat, Peshawar Road, Gate # 3, KDA Kohat',
                'phone' => '09229260332',
                'fax' => '09229260332',
                'email' => 'arokht@gmail.com',
                'users' => [
                    [
                        'name' => 'Nasira Abid',
                        'gender' => 'Female',
                        'date_of_birth' => '1990-04-15',
                        'basic_pay_scale' => '3',
                        'employee_status' => 'Permanent',
                        'district_id' => 25,
                        'designation' => 'Research Officer',
                        'present_duty' => 'In-charge Regional Lab Mardan',
                        'assigned_parameters' => 'Physical Chemical and Biological Parameters ',
                        'career_background' => 'Researcher',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'B-S Microbiology',
                        'email' => 'arokohat@gmail.com',
                        'phone_number' => '03348391658'
                    ],
                    [
                        'name' => 'Imran Ali',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1990-04-15',
                        'designation' => 'Laboratory Attendant',
                        'district_id' => 25,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Attendant',
                        'assigned_parameters' => 'Nil',
                        'career_background' => ' -',
                        'image' => 'users/avatar.png',
                        'email' => 'user@gmail.com',
                        'educational_background' => 'F-A',
                        'phone_number' => '03331234567',
                    ],
                ],
            ],
            [
                'name' => 'Mardan Laboratory',
                'latitude' => '34.1974',
                'longitude' => '72.0498',
                'focal_person_id',
                'logo' => 'images/default.jpg',
                'district_id' => 'Mardan',
                'division_id' => 'Mardan',
                'province_id' => 1,
                'address' => 'Near O/O SE PHE Cicle Mardan, Shamsi Road, Near Mardan Press Club, Mardan',
                'phone' => '09379230142',
                'fax' => '09379230142',
                'email' => 'aromdn@gmail.com',
                'users' => [
                    [
                        'name' => 'Mr Amar',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1995-02-14',
                        'designation' => 'Assistant Research Officer',
                        'district_id' => 6,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'In-charge Regional Lab Mardan',
                        'assigned_parameters' => 'Physical Chemical and Biological Parameters ',
                        'career_background' => 'Researcher',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'B-S Microbiology',
                        'email' => 'Amark7611@gmail.com',
                        'phone_number' => '03485319032'
                    ],
                    [
                        'name' => 'Faraz Ahmad',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1992-04-01',
                        'designation' => 'Laboratory Assistant',
                        'district_id' => 6,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Lab Assistant',
                        'assigned_parameters' => 'Physical and Chemical Parameters ',
                        'career_background' => 'Volunteer',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'B-A',
                        'email' => 'worldnature052@gmail.com',
                        'phone_number' => '03453006598'
                    ],
                    [
                        'name' => 'Yasir Khan',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1993-08-14',
                        'designation' => 'Laboratory Attendant',
                        'district_id' => 6,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Lab Attendant',
                        'assigned_parameters' => 'Assist in Testing',
                        'career_background' => 'Volunteer',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'DAE Civil',
                        'email' => 'Yasirmohamand12@gmail.com',
                        'phone_number' => '03139482740'
                    ],
                ],
            ],
            [
                'name' => 'Di Khan Laboratory',
                'latitude' => '31.8260',
                'longitude' => '70.9017',
                'focal_person_id',
                'logo' => 'images/default.jpg',
                'district_id' => 'D.I. Khan',
                'division_id' => 'D.I. Khan',
                'province_id' => 1,
                'address' => 'Near O/O SE / XEN PHED DI Khan, Shah Alam Abad Chowk DI Khan',
                'phone' => '09669280222',
                'fax' => '09669280222',
                'email' => 'arodik@gmail.com',
                'users' => [
                    [
                        'name' => 'Muhammad Naveed',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1995-03-07',
                        'designation' => 'Laboratory Assistant',
                        'district_id' => 33,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory Assistant',
                        'assigned_parameters' => 'Physical, chemical Microbial    ',
                        'career_background' => 'Laboratory, Technician    ',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'Diploma in MLT,FSC  ',
                        'email' => 'nk93832@gmail.com',
                        'phone_number' => '03401909690'
                    ],
                    [
                        'name' => 'Muhammad Taimur',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1994-04-01',
                        'designation' => 'Laboratory Attendant',
                        'district_id' => 33,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory Attendant',
                        'assigned_parameters' => 'Physical, chemical    ',
                        'career_background' => 'Laboratory, Attendant    ',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'Diploma in DAE Civil,BSC Computer scince   ',
                        'email' => '99taimur99@gmail.com',
                        'phone_number' => '03469783335'
                    ],
                ],
            ],
            [
                'name' => 'Bannu/lakki Laboratory',
                'latitude' => '32.9869',
                'longitude' => '70.6027',
                'focal_person_id',
                'logo' => 'images/default.jpg',
                'district_id' => 'Bannu',
                'division_id' => 'Bannu',
                'province_id' => 1,
                'address' => 'Near O/O XEN Lakki Marwat, District Complex Tajazai, Lakki Marwat',
                'phone' => '0969538336',
                'fax' => '0969538336',
                'email' => 'arolakki@gmail.com',
                'users' => [
                    [
                        'name' => 'Atif Ullah',
                        'gender' => 'Male',
                        'basic_pay_scale' => '16',
                        'date_of_birth' => '1984-09-02',
                        'designation' => 'Assistant Research Officer',
                        'district_id' => 30,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Water Quality Testing Laboratory at PHE Circle Bannu Lakki marwat',
                        'assigned_parameters' => 'Physical,Chemical and Microbial',
                        'career_background' => 'Teaching',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'M-S Chemistry',
                        'email' => 'atifullahchem498@gmail.com',
                        'phone_number' => '03451578786'
                    ],
                    [
                        'name' => 'Mohammad Farhan',
                        'gender' => 'Male',
                        'basic_pay_scale' => '6',
                        'date_of_birth' => '1987-07-01',
                        'designation' => 'Laboratory Assistant',
                        'district_id' => 30,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory Assistant',
                        'assigned_parameters' => 'Physical,Chemical and Microbial',
                        'career_background' => 'Laboratory Technician ',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'B-S Hons Pathology DMLT, 2  years Pathology Diploma',
                        'email' => 'mfarhan4158@gmail.com',
                        'phone_number' => '03329593534'
                    ],
                    [
                        'name' => 'Irfanullah Khan',
                        'gender' => 'Male',
                        'basic_pay_scale' => '3',
                        'date_of_birth' => '1997-02-15',
                        'designation' => 'Laboratory Attendant',
                        'district_id' => 30,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory Attendant',
                        'assigned_parameters' => 'Physical,Chemical and Microbial',
                        'career_background' => 'Laboratory Attendant',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'B-Sc Chemistry,',
                        'email' => fake()->email,
                        'phone_number' => '03048213120'
                    ],
                ],
            ],
            [
                'name' => 'Abbottabad Laboratory',
                'latitude' => '34.1690',
                'longitude' => '73.2110',
                'focal_person_id',
                'logo' => 'images/default.jpg',
                'district_id' => 'Abbottabad',
                'division_id' => 'Abbottabad',
                'province_id' => 1,
                'address' => 'Near O/O the SE PHE Circle Abbottabad, Kaghan Colony Mandian, Abbottabad',
                'phone' => '0992383211',
                'fax' => '0992383211',
                'email' => 'laboratory8@kpk.com',
                'users' => [
                    [
                        'name' => 'Farhad Ali',
                        'gender' => 'Male',
                        'basic_pay_scale' => '16',
                        'date_of_birth' => '1986-01-07',
                        'designation' => 'Assistant Research Officer',
                        'district_id' => 23,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'In-charge Regional Water Quality Testing Laboratory at PHE Division Abbottabad',
                        'assigned_parameters' => 'Physical,Chemical and Microbial',
                        'career_background' => 'Nil',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'M-Sc Chemistry',
                        'email' => 'brominechem@gmail.com',
                        'phone_number' => '03466492117'
                    ],
                    [
                        'name' => 'Muhammad Waseem Khan',
                        'gender' => 'Male',
                        'basic_pay_scale' => '8',
                        'date_of_birth' => '1992-11-01',
                        'designation' => 'Laboratory Assistant',
                        'district_id' => 23,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory Assistant',
                        'assigned_parameters' => 'Physical,Chemical and Microbial',
                        'career_background' => 'Laboratory Technition ',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'Bs(HONS)Medical Lab Technology',
                        'email' => 'wtanoli31@gmail.com',
                        'phone_number' => '03239837035'
                    ],
                    [
                        'name' => 'Muhammad Javed',
                        'gender' => 'Male',
                        'basic_pay_scale' => '6',
                        'date_of_birth' => '1985-05-07',
                        'designation' => 'Laboratory Attendant',
                        'district_id' => 23,
                        'employee_status' => 'Permanent',
                        'present_duty' => 'Laboratory Attendant',
                        'assigned_parameters' => 'Physical',
                        'career_background' => 'Nil',
                        'image' => 'users/avatar.png',
                        'educational_background' => 'Matriculation',
                        'email' => 'javedphed@gmail.com',
                        'phone_number' => '03075816371',
                    ],
                ],
            ],
        ];

        try {
            DB::beginTransaction();

            $systemAdministrator = User::query()
                ->create([
                    'name' => 'Adeel',
                    'email' => 'srophed@gmail.com',
                    'password' => Hash::make('Wqm+Mis1=2'),
                    'phone' => '03339656580',
                    'gender' => GenderEnum::Male->value,
                    'district_id' => 1,
                    'date_of_birth' => '1990-01-01',
                    'basic_pay_scale' => '18',
                    'employee_status' => 'Permanent',
                    'career_background' => 'N/A',
                    'image' => 'users/avatar.png',
                    'educational_background' => 'N/A',
                    'designation_id' => Designation::query()->firstOrCreate(['name' => 'Senior Research Officer'])->id,
                ]);

            $systemAdministrator->assignRole('system-administrator');

            foreach ($laboratories as $parentKey => $laboratory) {
                $division = $this->createDivision($laboratory['division_id']);
                $district = $this->createDistrict($laboratory['district_id'], $division->id);
                $focalPerson = User::query()
                    ->create([
                        'name' => $laboratory['users'][0]['name'],
                        'email' => $laboratory['users'][0]['email'],
                        'password' => Hash::make('Wqm+Mis1=2'),
                        'phone' => $laboratory['users'][0]['phone_number'],
                        'gender' => mb_strtolower($laboratory['users'][0]['gender']),
                        'district_id' => $laboratory['users'][0]['district_id'],
                        'date_of_birth' => $laboratory['users'][0]['date_of_birth'],
                        'basic_pay_scale' => $laboratory['users'][0]['basic_pay_scale'],
                        'employee_status' => $laboratory['users'][0]['employee_status'],
                        'career_background' => $laboratory['users'][0]['career_background'],
                        'image' => 'users/avatar.png',
                        'educational_background' => $laboratory['users'][0]['educational_background'],
                        'designation_id' => Designation::query()->firstOrCreate(['name' => $laboratory['users'][0]['designation']])->id,
                    ]);
                $focalPerson->assignRole('system-manager');

                $newLaboratory = Laboratory::query()
                    ->create([
                        'name' => $laboratory['name'],
                        'latitude' => $laboratory['latitude'],
                        'longitude' => $laboratory['longitude'],
                        'phone' => $laboratory['phone'],
                        'fax' => $laboratory['fax'],
                        'email' => $laboratory['email'],
                        'address' => $laboratory['address'],
                        'focal_person_id' => $focalPerson->id,
                        'logo' => $laboratory['logo'],
                        'district_id' => $district->id,
                        'division_id' => $division->id,
                        'province_id' => $laboratory['province_id'],
                    ]);
                $newLaboratory->users()
                    ->sync([$focalPerson->id => [
                        'present_duty' => $laboratory['users'][0]['present_duty'],
                        'assigned_parameters' => $laboratory['users'][0]['assigned_parameters'],
                    ]]);

                if (0 === $parentKey) {
                    $newLaboratory->users()
                        ->sync([$systemAdministrator->id => [
                            'present_duty' => 'System Administrator',
                            'assigned_parameters' => 'N/A',
                        ]]);
                }

                foreach ($laboratory['users'] as $key => $user) {
                    if ($key !== 0) {
                        $newUser = User::query()
                            ->create([
                                'name' => $user['name'],
                                'email' => $user['email'],
                                'password' => Hash::make('Wqm+Mis1=2'),
                                'phone' => $user['phone_number'],
                                'gender' => mb_strtolower($user['gender']),
                                'date_of_birth' => $user['date_of_birth'],
                                'basic_pay_scale' => $user['basic_pay_scale'],
                                'district_id' => $district->id,
                                'employee_status' => $user['employee_status'],
                                'career_background' => $user['career_background'],
                                'image' => 'users/avatar.png',
                                'educational_background' => $user['educational_background'],
                                'designation_id' => Designation::query()->firstOrCreate(['name' => $user['designation']])->id,
                            ]);

                        $newUser->laboratories()
                            ->sync([
                                $newLaboratory->id => [
                                    'present_duty' => $user['present_duty'],
                                    'assigned_parameters' => $user['assigned_parameters'],
                                ]
                            ]);
                        $newUser->assignRole('junior-clerk');
                    }
                }
            }
            DB::commit();
        } catch (\Exception $exception) {
            info($exception->getMessage());
            DB::rollBack();
        }
    }

    public function createDistrict(string $districtName, int $divisionId): District
    {
        return District::query()->firstOrCreate(['name' => $districtName, 'division_id' => $divisionId]);
    }

    public function createDivision(string $divisionName): Division
    {
        return Division::query()->firstOrCreate(['name' => $divisionName,'province_id' => 1]);
    }
}
