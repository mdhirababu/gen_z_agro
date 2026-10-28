@extends('layouts.farm')
@section('title', 'ডেটা এক্সপোর্ট')

@section('content')
    <div class="page-head">
        <div>
            <h1>খামারের সব ডেটা নামান</h1>
            <p>আপনার খামারের সব তথ্য আপনার নিজের কাছে রাখার জন্য।</p>
        </div>
    </div>

    <div class="panel h-auto">
        <div class="panel-body">
            <p>একটা ZIP ফাইল নামবে, তাতে আছে:</p>
            <ul>
                <li>গরু, ওজন, দুধ, চিকিৎসা ও টিকা, প্রজনন, খাবার</li>
                <li>গ্রাহক, বিক্রি, খরচ ও সব পেমেন্ট (<strong>বাতিল ও মোছা রেকর্ডসহ</strong>, পূর্ণ ইতিহাস)</li>
                <li>সদস্যদের তালিকা ও অডিট লগ</li>
                <li>প্রতিটি ফাইল Excel বা LibreOffice-এ খোলা যায়</li>
            </ul>

            <div class="alert alert-warning">
                <i class="bi bi-shield-exclamation me-1"></i>
                এতে গ্রাহকের নাম-ফোন ও সদস্যদের ইমেইলের মতো ব্যক্তিগত তথ্য থাকে। ফাইলটা নিরাপদ জায়গায় রাখুন, অন্যের সঙ্গে
                ছড়াবেন না।
            </div>

            <form method="GET" action="{{ route('farms.data-export.download', $farm) }}">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="photos" value="1" id="photos">
                    <label class="form-check-label" for="photos">গরুর ছবিও নিন (ফাইল বড় হবে)</label>
                </div>
                <button class="btn btn-primary"><i class="bi bi-download me-1"></i>ZIP নামান</button>
            </form>

            <div class="small text-muted mt-3">
                ঘণ্টায় সর্বোচ্চ ৩ বার নামানো যায়। প্রতিটি ডাউনলোড অডিট লগে লেখা থাকে। আপনার সাবস্ক্রিপশনের মেয়াদ শেষ হলেও
                এটা কাজ করে।
            </div>
        </div>
    </div>
@endsection
