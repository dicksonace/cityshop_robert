<?php

return [

    'contact' => [
        'email' => env('MARKETPLACE_SUPPORT_EMAIL', 'support@cityshop.com'),
        'phone' => env('MARKETPLACE_SUPPORT_PHONE', '+233 30 000 0000'),
        'whatsapp' => env('MARKETPLACE_SUPPORT_WHATSAPP', '+233 24 000 0000'),
        'address' => 'Oxford Street, Osu, Accra, Ghana',
        'hours' => 'Monday – Saturday, 8:00 AM – 6:00 PM GMT',
    ],

    'faq' => [
        [
            'category' => 'Buying on CityShop',
            'items' => [
                [
                    'question' => 'How do I place an order?',
                    'answer' => 'Browse products, add items to your cart, then proceed to checkout. Enter your delivery details, choose a payment method, and complete payment via Paystack. You will receive an order confirmation by email and SMS.',
                ],
                [
                    'question' => 'Do I need an account to shop?',
                    'answer' => 'You can browse products without an account, but you must register and log in to add items to your cart, checkout, track orders, save a wishlist, and leave reviews.',
                ],
                [
                    'question' => 'What payment methods are accepted?',
                    'answer' => 'We accept Mobile Money (MTN, Telecel, AirtelTigo), bank cards, and bank transfers through Paystack. All payments are processed securely at checkout.',
                ],
                [
                    'question' => 'Can I cancel my order?',
                    'answer' => 'You may cancel before the seller sends your item for delivery. Go to My Orders, open the order, and request cancellation. Once out for delivery, cancellation is no longer available — you may open a dispute if there is a problem.',
                ],
            ],
        ],
        [
            'category' => 'Delivery',
            'items' => [
                [
                    'question' => 'Who handles delivery?',
                    'answer' => 'Sellers deliver items directly to buyers. Each seller arranges their own delivery or courier within Ghana — CityShop does not ship orders itself.',
                ],
                [
                    'question' => 'How long does delivery take?',
                    'answer' => 'Delivery times vary by seller and location. Items marked "In Ghana" typically arrive within 1–5 business days in Greater Accra and 3–10 days nationwide.',
                ],
                [
                    'question' => 'What does "Free Delivery" mean?',
                    'answer' => 'Products with a Free Delivery badge are delivered by the seller at no extra charge. You pay only the product price at checkout with no additional delivery fee for that item.',
                ],
                [
                    'question' => 'How do I track my order?',
                    'answer' => 'When your order is out for delivery, the seller adds a courier name and tracking number. View these under My Orders → order details. You will also receive SMS and email updates when your order status changes.',
                ],
            ],
        ],
        [
            'category' => 'Selling on CityShop',
            'items' => [
                [
                    'question' => 'How do I become a seller?',
                    'answer' => 'Seller registration is by invitation only. Contact support with the subject "Become a Seller" and tell us about your business. If approved, we will email you a private registration link valid for 24 hours. Upload your Ghana Card, business documents (if applicable), and shop photo. Our team reviews applications within 1–3 business days.',
                ],
                [
                    'question' => 'What commission does CityShop charge?',
                    'answer' => 'CityShop charges a 10% commission on each completed sale. The remaining 90% is credited to your seller wallet after the buyer confirms delivery.',
                ],
                [
                    'question' => 'How do I get paid as a seller?',
                    'answer' => 'Earnings go to your seller wallet. Once funds are available, request a withdrawal to your Mobile Money number from the Wallet page. Withdrawals are reviewed and processed by our admin team.',
                ],
                [
                    'question' => 'How do I add product specifications?',
                    'answer' => 'When creating a product, select a category first. CityShop shows the relevant specification fields for that category — for example RAM and storage for laptops, or size and material for fashion items.',
                ],
            ],
        ],
        [
            'category' => 'Returns, Disputes & Buyer Protection',
            'items' => [
                [
                    'question' => 'What if I receive a wrong or damaged item?',
                    'answer' => 'Open a dispute from your order details page within 7 days of delivery. Describe the issue and our team will investigate. Funds may be held in escrow until the dispute is resolved.',
                ],
                [
                    'question' => 'How does buyer protection work?',
                    'answer' => 'Your payment is held securely until delivery is confirmed. If a seller fails to deliver or sends an item that does not match the listing, you can open a dispute for a refund or replacement.',
                ],
                [
                    'question' => 'Can I return a product?',
                    'answer' => 'Return policies depend on the seller and product category. Contact the seller through your order page first. If unresolved, open a dispute and CityShop support will mediate.',
                ],
            ],
        ],
        [
            'category' => 'Account & Security',
            'items' => [
                [
                    'question' => 'How do I reset my password?',
                    'answer' => 'Click Login, then "Forgot password?" Enter your registered email and follow the reset link sent to you. If you registered with a mobile number only, contact support for help.',
                ],
                [
                    'question' => 'Is my payment information safe?',
                    'answer' => 'Yes. CityShop does not store your card or Mobile Money PIN. All payments are handled by Paystack, a PCI-DSS compliant payment processor trusted across Africa.',
                ],
                [
                    'question' => 'How do I contact support?',
                    'answer' => 'Visit our Contact page to send a message, email support@cityshop.com, or WhatsApp us during business hours. Include your order number if your question is about a specific purchase.',
                ],
            ],
        ],
    ],

];
