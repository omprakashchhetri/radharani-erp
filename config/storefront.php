<?php

/*
 | Public website (storefront) reference lists. Categories, collections,
 | pieces and shop details are data (Website section of the ERP); these
 | are the fixed vocabularies the site's filters are built from.
 */
return [

    // A piece counts as "New" for this many days after it was first listed.
    'new_days' => 30,

    'budgets' => [
        ['slug' => '0-25000', 'label' => 'Under ₹25,000', 'min' => 0, 'max' => 25000,
            'small' => 'Under', 'strong' => '₹25,000', 'image' => '1611955167811-4711904bb9f8', 'alt' => 'Gold ring with diamonds'],
        ['slug' => '25000-75000', 'label' => '₹25,000 to ₹75,000', 'min' => 25000, 'max' => 75000,
            'small' => 'From', 'strong' => '₹25,000 to ₹75,000', 'image' => '1714733831162-0a6e849141be', 'alt' => 'Pair of gold drop earrings'],
        ['slug' => '75000-200000', 'label' => '₹75,000 to ₹2 lakh', 'min' => 75000, 'max' => 200000,
            'small' => 'From', 'strong' => '₹75,000 to ₹2 lakh', 'image' => '1721103418312-b0057a8c31c2', 'alt' => 'Gold necklace with pearls'],
        ['slug' => '200000-', 'label' => 'Above ₹2 lakh', 'min' => 200000, 'max' => null,
            'small' => 'Above', 'strong' => '₹2 lakh', 'image' => '1640183298005-3a4497cc6a37', 'alt' => 'Gold necklace and earring set in a display case'],
    ],

    'occasions' => [
        'bridal' => 'Bridal',
        'festive' => 'Festive',
        'daily' => 'Everyday',
        'anniversary' => 'Anniversary',
        'birthday' => 'Birthday',
        'engagement' => 'Engagement',
        'rakhi' => 'Raksha Bandhan',
    ],

    'audiences' => [
        'women' => 'Women',
        'men' => 'Men',
        'kids' => 'Kids',
    ],

    // The label shown next to a piece's size on the product page.
    'size_types' => [
        'ring' => 'Ring size',
        'bangle' => 'Bangle size',
        'chain' => 'Length',
    ],

    // Used until someone saves Website Settings.
    'defaults' => [
        'whatsapp' => '91XXXXXXXXXX',
        'phone' => '+91 XXXXX XXXXX',
        'address' => 'Main Road, [City]',
        'hours' => '10:30 AM to 8:30 PM, daily',
        'parking' => 'In front of the showroom',
        'maps_query' => 'Radharani Jewellery Works',
        'instagram' => 'https://instagram.com',
        'facebook' => 'https://facebook.com',
        'youtube' => 'https://youtube.com',
    ],
];
