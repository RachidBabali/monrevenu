
            <?php if (!empty($message)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-3.5 rounded-2xl text-sm font-semibold flex items-center gap-2">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-3.5 rounded-2xl text-sm font-semibold flex items-center gap-2">
                    <?= $error ?>
                </div>
            <?php endif; ?>