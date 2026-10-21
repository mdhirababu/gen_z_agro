{{-- বাবা পেজে x-data="{ vm: { open: false, url: '', title: '', sub: '' } }" থাকতে হবে --}}
<div class="sheet-backdrop" x-show="vm.open" x-transition.opacity style="display: none" @click.self="vm.open = false"
    @keydown.escape.window="vm.open = false">
    <div class="sheet" role="dialog" aria-modal="true">
        <form method="POST" :action="vm.url">
            @csrf

            <div class="sheet-head">
                <span class="ic rose"><i class="bi bi-slash-circle"></i></span>
                <div>
                    <h3 x-text="vm.title"></h3>
                    <p x-text="vm.sub"></p>
                </div>
            </div>

            <p class="sheet-note">
                আর্থিক রেকর্ড মুছে ফেলা হয় না, শুধু "বাতিল" হিসেবে থেকে যায়। কেন বাতিল করছেন, সেটা লিখে রাখুন।
            </p>

            <label for="void-reason" class="form-label">বাতিলের কারণ <span class="text-danger">*</span></label>
            <textarea id="void-reason" name="reason" rows="2" class="form-control" required minlength="3" maxlength="255"
                placeholder="যেমন: ভুল অঙ্ক লেখা হয়েছিল"></textarea>

            <div class="sheet-actions">
                <button type="button" class="btn btn-outline-secondary" @click="vm.open = false">ফিরে যান</button>
                <button type="submit" class="btn btn-danger">হ্যাঁ, বাতিল করুন</button>
            </div>
        </form>
    </div>
</div>
