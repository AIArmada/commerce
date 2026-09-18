<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Afghanistan\AfghanistanAddressFormatter;
use AIArmada\Addressing\Geography\Afghanistan\AfghanistanGeographyProvider;
use AIArmada\Addressing\Geography\Algeria\AlgeriaAddressFormatter;
use AIArmada\Addressing\Geography\Algeria\AlgeriaGeographyProvider;
use AIArmada\Addressing\Geography\Angola\AngolaAddressFormatter;
use AIArmada\Addressing\Geography\Angola\AngolaGeographyProvider;
use AIArmada\Addressing\Geography\Argentina\ArgentinaAddressFormatter;
use AIArmada\Addressing\Geography\Argentina\ArgentinaGeographyProvider;
use AIArmada\Addressing\Geography\Armenia\ArmeniaAddressFormatter;
use AIArmada\Addressing\Geography\Armenia\ArmeniaGeographyProvider;
use AIArmada\Addressing\Geography\Australia\AustraliaAddressFormatter;
use AIArmada\Addressing\Geography\Australia\AustraliaGeographyProvider;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanAddressFormatter;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanGeographyProvider;
use AIArmada\Addressing\Geography\Bahrain\BahrainAddressFormatter;
use AIArmada\Addressing\Geography\Bahrain\BahrainGeographyProvider;
use AIArmada\Addressing\Geography\Bangladesh\BangladeshAddressFormatter;
use AIArmada\Addressing\Geography\Bangladesh\BangladeshGeographyProvider;
use AIArmada\Addressing\Geography\Benin\BeninAddressFormatter;
use AIArmada\Addressing\Geography\Benin\BeninGeographyProvider;
use AIArmada\Addressing\Geography\Bhutan\BhutanAddressFormatter;
use AIArmada\Addressing\Geography\Bhutan\BhutanGeographyProvider;
use AIArmada\Addressing\Geography\Botswana\BotswanaAddressFormatter;
use AIArmada\Addressing\Geography\Botswana\BotswanaGeographyProvider;
use AIArmada\Addressing\Geography\Brazil\BrazilAddressFormatter;
use AIArmada\Addressing\Geography\Brazil\BrazilGeographyProvider;
use AIArmada\Addressing\Geography\Brunei\BruneiAddressFormatter;
use AIArmada\Addressing\Geography\Brunei\BruneiGeographyProvider;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoAddressFormatter;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoGeographyProvider;
use AIArmada\Addressing\Geography\Burundi\BurundiAddressFormatter;
use AIArmada\Addressing\Geography\Burundi\BurundiGeographyProvider;
use AIArmada\Addressing\Geography\Cambodia\CambodiaAddressFormatter;
use AIArmada\Addressing\Geography\Cambodia\CambodiaGeographyProvider;
use AIArmada\Addressing\Geography\Cameroon\CameroonAddressFormatter;
use AIArmada\Addressing\Geography\Cameroon\CameroonGeographyProvider;
use AIArmada\Addressing\Geography\Canada\CanadaAddressFormatter;
use AIArmada\Addressing\Geography\Canada\CanadaGeographyProvider;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeAddressFormatter;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeGeographyProvider;
use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicAddressFormatter;
use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicGeographyProvider;
use AIArmada\Addressing\Geography\Chad\ChadAddressFormatter;
use AIArmada\Addressing\Geography\Chad\ChadGeographyProvider;
use AIArmada\Addressing\Geography\China\ChinaAddressFormatter;
use AIArmada\Addressing\Geography\China\ChinaGeographyProvider;
use AIArmada\Addressing\Geography\Colombia\ColombiaAddressFormatter;
use AIArmada\Addressing\Geography\Colombia\ColombiaGeographyProvider;
use AIArmada\Addressing\Geography\Comoros\ComorosAddressFormatter;
use AIArmada\Addressing\Geography\Comoros\ComorosGeographyProvider;
use AIArmada\Addressing\Geography\Congo\CongoAddressFormatter;
use AIArmada\Addressing\Geography\Congo\CongoGeographyProvider;
use AIArmada\Addressing\Geography\Cyprus\CyprusAddressFormatter;
use AIArmada\Addressing\Geography\Cyprus\CyprusGeographyProvider;
use AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoAddressFormatter;
use AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoGeographyProvider;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiAddressFormatter;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiGeographyProvider;
use AIArmada\Addressing\Geography\Egypt\EgyptAddressFormatter;
use AIArmada\Addressing\Geography\Egypt\EgyptGeographyProvider;
use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaAddressFormatter;
use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaGeographyProvider;
use AIArmada\Addressing\Geography\Eritrea\EritreaAddressFormatter;
use AIArmada\Addressing\Geography\Eritrea\EritreaGeographyProvider;
use AIArmada\Addressing\Geography\Eswatini\EswatiniAddressFormatter;
use AIArmada\Addressing\Geography\Eswatini\EswatiniGeographyProvider;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaAddressFormatter;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaGeographyProvider;
use AIArmada\Addressing\Geography\France\FranceAddressFormatter;
use AIArmada\Addressing\Geography\France\FranceGeographyProvider;
use AIArmada\Addressing\Geography\Gabon\GabonAddressFormatter;
use AIArmada\Addressing\Geography\Gabon\GabonGeographyProvider;
use AIArmada\Addressing\Geography\Gambia\GambiaAddressFormatter;
use AIArmada\Addressing\Geography\Gambia\GambiaGeographyProvider;
use AIArmada\Addressing\Geography\Georgia\GeorgiaAddressFormatter;
use AIArmada\Addressing\Geography\Georgia\GeorgiaGeographyProvider;
use AIArmada\Addressing\Geography\Germany\GermanyAddressFormatter;
use AIArmada\Addressing\Geography\Germany\GermanyGeographyProvider;
use AIArmada\Addressing\Geography\Ghana\GhanaAddressFormatter;
use AIArmada\Addressing\Geography\Ghana\GhanaGeographyProvider;
use AIArmada\Addressing\Geography\Guinea\GuineaAddressFormatter;
use AIArmada\Addressing\Geography\Guinea\GuineaGeographyProvider;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauAddressFormatter;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauGeographyProvider;
use AIArmada\Addressing\Geography\HongKong\HongKongAddressFormatter;
use AIArmada\Addressing\Geography\HongKong\HongKongGeographyProvider;
use AIArmada\Addressing\Geography\India\IndiaAddressFormatter;
use AIArmada\Addressing\Geography\India\IndiaGeographyProvider;
use AIArmada\Addressing\Geography\Indonesia\IndonesiaAddressFormatter;
use AIArmada\Addressing\Geography\Indonesia\IndonesiaGeographyProvider;
use AIArmada\Addressing\Geography\Iran\IranAddressFormatter;
use AIArmada\Addressing\Geography\Iran\IranGeographyProvider;
use AIArmada\Addressing\Geography\Iraq\IraqAddressFormatter;
use AIArmada\Addressing\Geography\Iraq\IraqGeographyProvider;
use AIArmada\Addressing\Geography\Israel\IsraelAddressFormatter;
use AIArmada\Addressing\Geography\Israel\IsraelGeographyProvider;
use AIArmada\Addressing\Geography\Italy\ItalyAddressFormatter;
use AIArmada\Addressing\Geography\Italy\ItalyGeographyProvider;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastAddressFormatter;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastGeographyProvider;
use AIArmada\Addressing\Geography\Japan\JapanAddressFormatter;
use AIArmada\Addressing\Geography\Japan\JapanGeographyProvider;
use AIArmada\Addressing\Geography\Jordan\JordanAddressFormatter;
use AIArmada\Addressing\Geography\Jordan\JordanGeographyProvider;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanAddressFormatter;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanGeographyProvider;
use AIArmada\Addressing\Geography\Kenya\KenyaAddressFormatter;
use AIArmada\Addressing\Geography\Kenya\KenyaGeographyProvider;
use AIArmada\Addressing\Geography\Kuwait\KuwaitAddressFormatter;
use AIArmada\Addressing\Geography\Kuwait\KuwaitGeographyProvider;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanAddressFormatter;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanGeographyProvider;
use AIArmada\Addressing\Geography\Laos\LaosAddressFormatter;
use AIArmada\Addressing\Geography\Laos\LaosGeographyProvider;
use AIArmada\Addressing\Geography\Lebanon\LebanonAddressFormatter;
use AIArmada\Addressing\Geography\Lebanon\LebanonGeographyProvider;
use AIArmada\Addressing\Geography\Lesotho\LesothoAddressFormatter;
use AIArmada\Addressing\Geography\Lesotho\LesothoGeographyProvider;
use AIArmada\Addressing\Geography\Liberia\LiberiaAddressFormatter;
use AIArmada\Addressing\Geography\Liberia\LiberiaGeographyProvider;
use AIArmada\Addressing\Geography\Libya\LibyaAddressFormatter;
use AIArmada\Addressing\Geography\Libya\LibyaGeographyProvider;
use AIArmada\Addressing\Geography\Madagascar\MadagascarAddressFormatter;
use AIArmada\Addressing\Geography\Madagascar\MadagascarGeographyProvider;
use AIArmada\Addressing\Geography\Malawi\MalawiAddressFormatter;
use AIArmada\Addressing\Geography\Malawi\MalawiGeographyProvider;
use AIArmada\Addressing\Geography\Malaysia\MalaysiaAddressFormatter;
use AIArmada\Addressing\Geography\Malaysia\MalaysiaGeographyProvider;
use AIArmada\Addressing\Geography\Maldives\MaldivesAddressFormatter;
use AIArmada\Addressing\Geography\Maldives\MaldivesGeographyProvider;
use AIArmada\Addressing\Geography\Mali\MaliAddressFormatter;
use AIArmada\Addressing\Geography\Mali\MaliGeographyProvider;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaAddressFormatter;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaGeographyProvider;
use AIArmada\Addressing\Geography\Mauritius\MauritiusAddressFormatter;
use AIArmada\Addressing\Geography\Mauritius\MauritiusGeographyProvider;
use AIArmada\Addressing\Geography\Mexico\MexicoAddressFormatter;
use AIArmada\Addressing\Geography\Mexico\MexicoGeographyProvider;
use AIArmada\Addressing\Geography\Mongolia\MongoliaAddressFormatter;
use AIArmada\Addressing\Geography\Mongolia\MongoliaGeographyProvider;
use AIArmada\Addressing\Geography\Morocco\MoroccoAddressFormatter;
use AIArmada\Addressing\Geography\Morocco\MoroccoGeographyProvider;
use AIArmada\Addressing\Geography\Mozambique\MozambiqueAddressFormatter;
use AIArmada\Addressing\Geography\Mozambique\MozambiqueGeographyProvider;
use AIArmada\Addressing\Geography\Myanmar\MyanmarAddressFormatter;
use AIArmada\Addressing\Geography\Myanmar\MyanmarGeographyProvider;
use AIArmada\Addressing\Geography\Namibia\NamibiaAddressFormatter;
use AIArmada\Addressing\Geography\Namibia\NamibiaGeographyProvider;
use AIArmada\Addressing\Geography\Nepal\NepalAddressFormatter;
use AIArmada\Addressing\Geography\Nepal\NepalGeographyProvider;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsAddressFormatter;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsGeographyProvider;
use AIArmada\Addressing\Geography\Niger\NigerAddressFormatter;
use AIArmada\Addressing\Geography\Niger\NigerGeographyProvider;
use AIArmada\Addressing\Geography\Nigeria\NigeriaAddressFormatter;
use AIArmada\Addressing\Geography\Nigeria\NigeriaGeographyProvider;
use AIArmada\Addressing\Geography\NorthKorea\NorthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\NorthKorea\NorthKoreaGeographyProvider;
use AIArmada\Addressing\Geography\Oman\OmanAddressFormatter;
use AIArmada\Addressing\Geography\Oman\OmanGeographyProvider;
use AIArmada\Addressing\Geography\Pakistan\PakistanAddressFormatter;
use AIArmada\Addressing\Geography\Pakistan\PakistanGeographyProvider;
use AIArmada\Addressing\Geography\Palestine\PalestineAddressFormatter;
use AIArmada\Addressing\Geography\Palestine\PalestineGeographyProvider;
use AIArmada\Addressing\Geography\Peru\PeruAddressFormatter;
use AIArmada\Addressing\Geography\Peru\PeruGeographyProvider;
use AIArmada\Addressing\Geography\Philippines\PhilippinesAddressFormatter;
use AIArmada\Addressing\Geography\Philippines\PhilippinesGeographyProvider;
use AIArmada\Addressing\Geography\Poland\PolandAddressFormatter;
use AIArmada\Addressing\Geography\Poland\PolandGeographyProvider;
use AIArmada\Addressing\Geography\Qatar\QatarAddressFormatter;
use AIArmada\Addressing\Geography\Qatar\QatarGeographyProvider;
use AIArmada\Addressing\Geography\Russia\RussiaAddressFormatter;
use AIArmada\Addressing\Geography\Russia\RussiaGeographyProvider;
use AIArmada\Addressing\Geography\Rwanda\RwandaAddressFormatter;
use AIArmada\Addressing\Geography\Rwanda\RwandaGeographyProvider;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeAddressFormatter;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeGeographyProvider;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaAddressFormatter;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaGeographyProvider;
use AIArmada\Addressing\Geography\Senegal\SenegalAddressFormatter;
use AIArmada\Addressing\Geography\Senegal\SenegalGeographyProvider;
use AIArmada\Addressing\Geography\Seychelles\SeychellesAddressFormatter;
use AIArmada\Addressing\Geography\Seychelles\SeychellesGeographyProvider;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneAddressFormatter;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneGeographyProvider;
use AIArmada\Addressing\Geography\Singapore\SingaporeAddressFormatter;
use AIArmada\Addressing\Geography\Singapore\SingaporeGeographyProvider;
use AIArmada\Addressing\Geography\Somalia\SomaliaAddressFormatter;
use AIArmada\Addressing\Geography\Somalia\SomaliaGeographyProvider;
use AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaAddressFormatter;
use AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaGeographyProvider;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaGeographyProvider;
use AIArmada\Addressing\Geography\SouthSudan\SouthSudanAddressFormatter;
use AIArmada\Addressing\Geography\SouthSudan\SouthSudanGeographyProvider;
use AIArmada\Addressing\Geography\Spain\SpainAddressFormatter;
use AIArmada\Addressing\Geography\Spain\SpainGeographyProvider;
use AIArmada\Addressing\Geography\SriLanka\SriLankaAddressFormatter;
use AIArmada\Addressing\Geography\SriLanka\SriLankaGeographyProvider;
use AIArmada\Addressing\Geography\Sudan\SudanAddressFormatter;
use AIArmada\Addressing\Geography\Sudan\SudanGeographyProvider;
use AIArmada\Addressing\Geography\Syria\SyriaAddressFormatter;
use AIArmada\Addressing\Geography\Syria\SyriaGeographyProvider;
use AIArmada\Addressing\Geography\Taiwan\TaiwanAddressFormatter;
use AIArmada\Addressing\Geography\Taiwan\TaiwanGeographyProvider;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanAddressFormatter;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanGeographyProvider;
use AIArmada\Addressing\Geography\Tanzania\TanzaniaAddressFormatter;
use AIArmada\Addressing\Geography\Tanzania\TanzaniaGeographyProvider;
use AIArmada\Addressing\Geography\Thailand\ThailandAddressFormatter;
use AIArmada\Addressing\Geography\Thailand\ThailandGeographyProvider;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteAddressFormatter;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteGeographyProvider;
use AIArmada\Addressing\Geography\Togo\TogoAddressFormatter;
use AIArmada\Addressing\Geography\Togo\TogoGeographyProvider;
use AIArmada\Addressing\Geography\Tunisia\TunisiaAddressFormatter;
use AIArmada\Addressing\Geography\Tunisia\TunisiaGeographyProvider;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeAddressFormatter;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeGeographyProvider;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanAddressFormatter;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanGeographyProvider;
use AIArmada\Addressing\Geography\Uganda\UgandaAddressFormatter;
use AIArmada\Addressing\Geography\Uganda\UgandaGeographyProvider;
use AIArmada\Addressing\Geography\Ukraine\UkraineAddressFormatter;
use AIArmada\Addressing\Geography\Ukraine\UkraineGeographyProvider;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesAddressFormatter;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesGeographyProvider;
use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomAddressFormatter;
use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomGeographyProvider;
use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesAddressFormatter;
use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesGeographyProvider;
use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanAddressFormatter;
use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanGeographyProvider;
use AIArmada\Addressing\Geography\Vietnam\VietnamAddressFormatter;
use AIArmada\Addressing\Geography\Vietnam\VietnamGeographyProvider;
use AIArmada\Addressing\Geography\Yemen\YemenAddressFormatter;
use AIArmada\Addressing\Geography\Yemen\YemenGeographyProvider;
use AIArmada\Addressing\Geography\Zambia\ZambiaAddressFormatter;
use AIArmada\Addressing\Geography\Zambia\ZambiaGeographyProvider;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweAddressFormatter;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweGeographyProvider;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\AddressSnapshot;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\AddressingTableResolver;

return [
    'database' => [
        'json_column_type' => env('ADDRESSING_JSON_COLUMN_TYPE', 'jsonb'),
        'tables' => AddressingTableResolver::defaults(),
    ],

    'models' => [
        'country' => AddressCountry::class,
        'state' => State::class,
        'city' => City::class,
        'area' => AddressArea::class,
        'address' => Address::class,
        'snapshot' => AddressSnapshot::class,
    ],

    'features' => [
        'owner' => [
            'enabled' => env('ADDRESSING_OWNER_ENABLED', true),
            'include_global' => env('ADDRESSING_OWNER_INCLUDE_GLOBAL', false),
            'auto_assign_on_create' => env('ADDRESSING_OWNER_AUTO_ASSIGN', true),
        ],
    ],

    'geography' => [
        // Add country providers here; the core package remains country-neutral.
        'providers' => [
            MalaysiaGeographyProvider::class,
            SingaporeGeographyProvider::class,
            IndonesiaGeographyProvider::class,
            BruneiGeographyProvider::class,
            BahrainGeographyProvider::class,
            BangladeshGeographyProvider::class,
            EgyptGeographyProvider::class,
            IndiaGeographyProvider::class,
            JordanGeographyProvider::class,
            KuwaitGeographyProvider::class,
            MoroccoGeographyProvider::class,
            OmanGeographyProvider::class,
            PakistanGeographyProvider::class,
            QatarGeographyProvider::class,
            SaudiArabiaGeographyProvider::class,
            SouthAfricaGeographyProvider::class,
            TurkiyeGeographyProvider::class,
            UnitedArabEmiratesGeographyProvider::class,
            UnitedKingdomGeographyProvider::class,
            ChinaGeographyProvider::class,
            RussiaGeographyProvider::class,
            GermanyGeographyProvider::class,
            FranceGeographyProvider::class,
            ItalyGeographyProvider::class,
            JapanGeographyProvider::class,
            UnitedStatesGeographyProvider::class,
            SpainGeographyProvider::class,
            PolandGeographyProvider::class,
            NetherlandsGeographyProvider::class,
            NigeriaGeographyProvider::class,
            EthiopiaGeographyProvider::class,
            DemocraticRepublicOfCongoGeographyProvider::class,
            TanzaniaGeographyProvider::class,
            KenyaGeographyProvider::class,
            SudanGeographyProvider::class,
            UgandaGeographyProvider::class,
            AlgeriaGeographyProvider::class,
            BrazilGeographyProvider::class,
            MexicoGeographyProvider::class,
            CanadaGeographyProvider::class,
            AustraliaGeographyProvider::class,
            ArgentinaGeographyProvider::class,
            ColombiaGeographyProvider::class,
            PeruGeographyProvider::class,
            VietnamGeographyProvider::class,
            ThailandGeographyProvider::class,
            PhilippinesGeographyProvider::class,
            SouthKoreaGeographyProvider::class,
            TaiwanGeographyProvider::class,
            UkraineGeographyProvider::class,
            IraqGeographyProvider::class,
            GhanaGeographyProvider::class,
            AngolaGeographyProvider::class,
            CameroonGeographyProvider::class,
            MadagascarGeographyProvider::class,
            AfghanistanGeographyProvider::class,
            MozambiqueGeographyProvider::class,
            UzbekistanGeographyProvider::class,
            MyanmarGeographyProvider::class,
            CambodiaGeographyProvider::class,
            LaosGeographyProvider::class,
            TimorLesteGeographyProvider::class,
            ArmeniaGeographyProvider::class,
            AzerbaijanGeographyProvider::class,
            BhutanGeographyProvider::class,
            CyprusGeographyProvider::class,
            GeorgiaGeographyProvider::class,
            HongKongGeographyProvider::class,
            IranGeographyProvider::class,
            IsraelGeographyProvider::class,
            KazakhstanGeographyProvider::class,
            KyrgyzstanGeographyProvider::class,
            LebanonGeographyProvider::class,
            MaldivesGeographyProvider::class,
            MongoliaGeographyProvider::class,
            NepalGeographyProvider::class,
            NorthKoreaGeographyProvider::class,
            PalestineGeographyProvider::class,
            SriLankaGeographyProvider::class,
            SyriaGeographyProvider::class,
            TajikistanGeographyProvider::class,
            TurkmenistanGeographyProvider::class,
            YemenGeographyProvider::class,
            BeninGeographyProvider::class,
            BotswanaGeographyProvider::class,
            BurkinaFasoGeographyProvider::class,
            BurundiGeographyProvider::class,
            CapeVerdeGeographyProvider::class,
            CentralAfricanRepublicGeographyProvider::class,
            ChadGeographyProvider::class,
            ComorosGeographyProvider::class,
            CongoGeographyProvider::class,
            DjiboutiGeographyProvider::class,
            EquatorialGuineaGeographyProvider::class,
            EritreaGeographyProvider::class,
            EswatiniGeographyProvider::class,
            GabonGeographyProvider::class,
            GambiaGeographyProvider::class,
            GuineaGeographyProvider::class,
            GuineaBissauGeographyProvider::class,
            IvoryCoastGeographyProvider::class,
            LesothoGeographyProvider::class,
            LiberiaGeographyProvider::class,
            LibyaGeographyProvider::class,
            MalawiGeographyProvider::class,
            MaliGeographyProvider::class,
            MauritaniaGeographyProvider::class,
            MauritiusGeographyProvider::class,
            NamibiaGeographyProvider::class,
            NigerGeographyProvider::class,
            RwandaGeographyProvider::class,
            SaoTomeAndPrincipeGeographyProvider::class,
            SenegalGeographyProvider::class,
            SeychellesGeographyProvider::class,
            SierraLeoneGeographyProvider::class,
            SomaliaGeographyProvider::class,
            SouthSudanGeographyProvider::class,
            TogoGeographyProvider::class,
            TunisiaGeographyProvider::class,
            ZambiaGeographyProvider::class,
            ZimbabweGeographyProvider::class,
        ],
    ],

    'formatters' => [
        MalaysiaAddressFormatter::class,
        SingaporeAddressFormatter::class,
        IndonesiaAddressFormatter::class,
        BruneiAddressFormatter::class,
        BahrainAddressFormatter::class,
        BangladeshAddressFormatter::class,
        EgyptAddressFormatter::class,
        IndiaAddressFormatter::class,
        JordanAddressFormatter::class,
        KuwaitAddressFormatter::class,
        MoroccoAddressFormatter::class,
        OmanAddressFormatter::class,
        PakistanAddressFormatter::class,
        QatarAddressFormatter::class,
        SaudiArabiaAddressFormatter::class,
        SouthAfricaAddressFormatter::class,
        TurkiyeAddressFormatter::class,
        UnitedArabEmiratesAddressFormatter::class,
        UnitedKingdomAddressFormatter::class,
        ChinaAddressFormatter::class,
        RussiaAddressFormatter::class,
        GermanyAddressFormatter::class,
        FranceAddressFormatter::class,
        ItalyAddressFormatter::class,
        JapanAddressFormatter::class,
        UnitedStatesAddressFormatter::class,
        SpainAddressFormatter::class,
        PolandAddressFormatter::class,
        NetherlandsAddressFormatter::class,
        NigeriaAddressFormatter::class,
        EthiopiaAddressFormatter::class,
        DemocraticRepublicOfCongoAddressFormatter::class,
        TanzaniaAddressFormatter::class,
        KenyaAddressFormatter::class,
        SudanAddressFormatter::class,
        UgandaAddressFormatter::class,
        AlgeriaAddressFormatter::class,
        BrazilAddressFormatter::class,
        MexicoAddressFormatter::class,
        CanadaAddressFormatter::class,
        AustraliaAddressFormatter::class,
        ArgentinaAddressFormatter::class,
        ColombiaAddressFormatter::class,
        PeruAddressFormatter::class,
        VietnamAddressFormatter::class,
        ThailandAddressFormatter::class,
        PhilippinesAddressFormatter::class,
        SouthKoreaAddressFormatter::class,
        TaiwanAddressFormatter::class,
        UkraineAddressFormatter::class,
        IraqAddressFormatter::class,
        GhanaAddressFormatter::class,
        AngolaAddressFormatter::class,
        CameroonAddressFormatter::class,
        MadagascarAddressFormatter::class,
        AfghanistanAddressFormatter::class,
        MozambiqueAddressFormatter::class,
        UzbekistanAddressFormatter::class,
        MyanmarAddressFormatter::class,
        CambodiaAddressFormatter::class,
        LaosAddressFormatter::class,
        TimorLesteAddressFormatter::class,
        ArmeniaAddressFormatter::class,
        AzerbaijanAddressFormatter::class,
        BhutanAddressFormatter::class,
        CyprusAddressFormatter::class,
        GeorgiaAddressFormatter::class,
        HongKongAddressFormatter::class,
        IranAddressFormatter::class,
        IsraelAddressFormatter::class,
        KazakhstanAddressFormatter::class,
        KyrgyzstanAddressFormatter::class,
        LebanonAddressFormatter::class,
        MaldivesAddressFormatter::class,
        MongoliaAddressFormatter::class,
        NepalAddressFormatter::class,
        NorthKoreaAddressFormatter::class,
        PalestineAddressFormatter::class,
        SriLankaAddressFormatter::class,
        SyriaAddressFormatter::class,
        TajikistanAddressFormatter::class,
        TurkmenistanAddressFormatter::class,
        YemenAddressFormatter::class,
        BeninAddressFormatter::class,
        BotswanaAddressFormatter::class,
        BurkinaFasoAddressFormatter::class,
        BurundiAddressFormatter::class,
        CapeVerdeAddressFormatter::class,
        CentralAfricanRepublicAddressFormatter::class,
        ChadAddressFormatter::class,
        ComorosAddressFormatter::class,
        CongoAddressFormatter::class,
        DjiboutiAddressFormatter::class,
        EquatorialGuineaAddressFormatter::class,
        EritreaAddressFormatter::class,
        EswatiniAddressFormatter::class,
        GabonAddressFormatter::class,
        GambiaAddressFormatter::class,
        GuineaAddressFormatter::class,
        GuineaBissauAddressFormatter::class,
        IvoryCoastAddressFormatter::class,
        LesothoAddressFormatter::class,
        LiberiaAddressFormatter::class,
        LibyaAddressFormatter::class,
        MalawiAddressFormatter::class,
        MaliAddressFormatter::class,
        MauritaniaAddressFormatter::class,
        MauritiusAddressFormatter::class,
        NamibiaAddressFormatter::class,
        NigerAddressFormatter::class,
        RwandaAddressFormatter::class,
        SaoTomeAndPrincipeAddressFormatter::class,
        SenegalAddressFormatter::class,
        SeychellesAddressFormatter::class,
        SierraLeoneAddressFormatter::class,
        SomaliaAddressFormatter::class,
        SouthSudanAddressFormatter::class,
        TogoAddressFormatter::class,
        TunisiaAddressFormatter::class,
        ZambiaAddressFormatter::class,
        ZimbabweAddressFormatter::class,
    ],

    'defaults' => [
        'country_code' => env('ADDRESS_DEFAULT_COUNTRY_CODE'),
        'locale' => env('ADDRESS_DEFAULT_LOCALE'),
    ],

    'area_sources' => [
        // App\Addressing\MalaysiaAddressAreaSource::class,
    ],

    'onemap' => [
        'base_url' => env('ONEMAP_BASE_URL', 'https://www.onemap.gov.sg/api'),
        'email' => env('ONEMAP_EMAIL'),
        'password' => env('ONEMAP_PASSWORD'),
        'timeout' => 10,
        'retries' => 2,
    ],
];
