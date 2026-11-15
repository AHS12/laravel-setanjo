<?php

return [
    'forbidden' => 'সেটিংস ড্যাশবোর্ড খোলার অনুমতি আপনার নেই।',
    'readonly' => 'আপনার জন্য সেটিংস ড্যাশবোর্ডটি শুধু দেখার জন্য।',
    'unprotected' => 'এখানে সেটিংস ড্যাশবোর্ড খুলতে সাইন-ইন করা ব্যবহারকারী প্রয়োজন। setanjo.dashboard.middleware-এ একটি auth মিডলওয়্যার বা Authorize যোগ করুন।',
    'locked' => 'সেটিং [:key] লক করা আছে, এখান থেকে পরিবর্তন করা যাবে না।',
    'unknown_scope' => 'স্কোপ টাইপ [:type] ড্যাশবোর্ডের জন্য কনফিগার করা নেই।',
    'scope_not_found' => '[:id] আইডির কোনো [:type] রেকর্ড নেই।',
    'scope_hidden' => '[:type] #[:id] আছে, কিন্তু একটি গ্লোবাল স্কোপ এটিকে ড্যাশবোর্ড থেকে লুকিয়ে রাখছে। setanjo.scopes-এ এই টাইপের জন্য without_scopes সেট করুন।',
    'history_disabled' => 'হিস্ট্রি চালু নেই। setanjo.history.enabled true করুন।',
    'invalid_import' => 'ফাইলটি সঠিক setanjo এক্সপোর্ট নয়।',
];
