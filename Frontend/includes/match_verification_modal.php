<!-- =========================================================
     GLOBAL MODAL OVERLAY (MATCH VERIFICATION)
     ========================================================= -->
<div
    id="modalOverlay"
    class="fixed inset-0 z-[100] hidden items-center justify-center
           bg-black bg-opacity-70 backdrop-blur-sm
           px-4 py-6"
    aria-hidden="true"
>

    <!-- =====================================================
         MODAL A — MATCH DETAILS
         ===================================================== -->
    <div
        id="matchDetailsModal"
        class="relative hidden w-full max-w-2xl
               max-h-[90vh] overflow-y-auto
               rounded-2xl border border-white/10
               bg-[#1a1511] shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="matchTitle"
    >
        <!-- Close Button -->
        <button
            id="closeMatchButton"
            type="button"
            aria-label="Close"
            class="absolute right-4 top-4 z-20
                   flex h-9 w-9 items-center justify-center
                   rounded-full bg-black/50
                   text-2xl text-white/70
                   backdrop-blur-md
                   transition hover:bg-black/70
                   hover:text-white"
        >
            &times;
        </button>

        <!-- =================================================
             HEADER INFO (IMAGE + TEXT SIDE-BY-SIDE)
             ================================================= -->
        <div class="flex flex-col-reverse sm:flex-row gap-5 p-5 sm:p-6 border-b border-white/5">
            
            <!-- Text Details (Left Side) -->
            <div class="flex-1 space-y-3 pt-2 sm:pt-0">
                <!-- Category & Report ID -->
                <div class="flex flex-wrap items-center gap-2">
                    <span id="matchCategory" class="text-xs font-semibold uppercase tracking-wider text-[#ff8a5c]">—</span>
                    <span class="text-white/25">•</span>
                    <span id="matchReportId" class="text-xs text-white/50">—</span>
                </div>
                
                <!-- Title -->
                <h2 id="matchTitle" class="pr-8 text-xl font-bold tracking-tight text-white sm:text-2xl">—</h2>
                
                <!-- Location & Date -->
                <div class="flex flex-col gap-2 mt-2">
                    <p class="text-sm font-medium text-white/85 flex items-center gap-2">
                        <span class="text-white/40">📍</span> <span id="matchLocation">—</span>
                    </p>
                    <p class="text-sm font-medium text-white/85 flex items-center gap-2">
                        <span class="text-white/40">📅</span> <span id="matchDate">—</span>
                    </p>
                </div>
            </div>

            <!-- Image (Right Side - Square) -->
            <div class="relative shrink-0 sm:w-36 sm:h-36 w-full h-48 rounded-xl overflow-hidden border border-white/10 shadow-lg">
                <img id="matchItemImage" src="" alt="Matched item" class="h-full w-full object-cover">
                <!-- AI Badge inside Image -->
                <div class="absolute bottom-1.5 left-1.5 right-1.5 rounded bg-[#1a1511]/90 px-1 py-1 text-center text-[10px] font-bold tracking-wider text-[#ff8a5c] backdrop-blur-md">
                    ✨ AI MATCH
                </div>
            </div>
        </div>

        <!-- =================================================
             CONTENT (SCORES, REASONS, BUTTONS)
             ================================================= -->
        <div class="space-y-6 p-5 sm:p-6">

            <!-- =================================================
                 AI MATCH ANALYSIS
                 ================================================= -->
            <section class="rounded-2xl border border-white/10 bg-[#241d18] p-4 sm:p-5">
                <!-- Section Header -->
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-white">
                        ✨ AI Match Analysis
                    </h3>
                    <span class="rounded-full bg-green-400/10 px-2.5 py-1 text-[11px] font-medium text-green-400">
                        Analysis Complete
                    </span>
                </div>

                <!-- Visual + Digital DNA Scores -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Visual Match -->
                    <div class="rounded-xl border border-white/5 bg-[#1a1511] p-4">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <span class="text-xs text-white/55">Visual Match Confidence</span>
                            <span id="visualScore" class="text-lg font-bold text-green-400">0%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-white/10">
                            <div id="visualProgress" class="h-full rounded-full bg-green-400 transition-all duration-700" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Digital DNA -->
                    <div class="rounded-xl border border-white/5 bg-[#1a1511] p-4">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <span class="text-xs text-white/55">Digital DNA Similarity</span>
                            <span id="dnaScore" class="text-lg font-bold text-green-400">0%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-white/10">
                            <div id="dnaProgress" class="h-full rounded-full bg-green-400 transition-all duration-700" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <!-- Safe Match Reasons -->
                <div class="mt-6">
                    <h4 class="mb-3 text-sm font-semibold text-white">Why this match was suggested</h4>
                    <ul id="safeMatchReasons" class="space-y-2"></ul>
                </div>
            </section>

            <!-- =================================================
                 PRIVACY / SECURITY WARNING
                 ================================================= -->
            <div class="rounded-xl border border-[#ff8a5c]/15 bg-[#ff8a5c]/5 px-4 py-3">
                <div class="flex gap-3">
                    <span class="mt-0.5 text-[#ff8a5c]">🔐</span>
                    <p class="text-xs leading-relaxed text-white/60">
                        AI matching does not verify ownership. Additional private ownership proof is required before the item can be released.
                    </p>
                </div>
            </div>

            <!-- =================================================
                 ACTION BUTTONS
                 ================================================= -->
            <div class="flex flex-col gap-3 sm:flex-row">
                <button
                    id="rejectMatchButton"
                    type="button"
                    class="flex-1 rounded-xl border border-red-400/50 bg-transparent px-5 py-3 font-semibold text-red-400 transition hover:bg-red-400/10 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Reject Match
                </button>

                <button
                    id="claimItemButton"
                    type="button"
                    class="flex-1 rounded-xl bg-[#ff8a5c] px-5 py-3 font-semibold text-[#1a1511] shadow-lg shadow-[#ff8a5c]/10 transition hover:bg-[#ff9a72] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Claim Item →
                </button>
            </div>

            <!-- Fraud Warning -->
            <p class="text-center text-[10px] leading-relaxed text-white/30">
                *Fraudulent claims are monitored and may lead to account suspension.
            </p>
        </div>
    </div>

    <!-- =====================================================
         MODAL B — OWNER VERIFICATION
         ===================================================== -->
    <div
        id="verificationModal"
        class="relative hidden w-full max-w-lg
               rounded-2xl border border-white/10
               bg-[#1a1511] shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="verificationTitle"
    >
        <!-- Close -->
        <button
            id="closeVerificationButton"
            type="button"
            aria-label="Close verification"
            class="absolute right-4 top-4
                   flex h-9 w-9 items-center justify-center
                   rounded-full bg-white/5
                   text-2xl text-white/60
                   transition hover:bg-white/10 hover:text-white"
        >
            &times;
        </button>

        <div class="p-6 sm:p-7">
            <!-- Icon -->
            <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-[#ff8a5c]/10 text-xl">
                🔐
            </div>

            <!-- Title -->
            <h2 id="verificationTitle" class="pr-10 text-2xl font-bold text-white">Verify Ownership</h2>

            <!-- Prompt -->
            <p class="mt-2 text-sm leading-relaxed text-white/55">
                Provide a detail that can help the founder verify that you are the rightful owner.
                Examples: a unique mark, hidden feature, contents, purchase detail, or another detail not visible in the listing.
            </p>

            <!-- PROOF FORM -->
            <form id="verificationForm" class="mt-6" novalidate>
                <label for="proofText" class="mb-2 block text-sm font-medium text-white/80">Ownership Proof</label>
                <textarea
                    id="proofText"
                    name="proof_text"
                    rows="5"
                    maxlength="1000"
                    required
                    placeholder="Enter a detail that proves your ownership..."
                    class="w-full resize-none rounded-xl border border-white/10 bg-[#241d18] px-4 py-3 text-sm text-white outline-none placeholder:text-white/25 transition focus:border-[#ff8a5c]/60 focus:ring-2 focus:ring-[#ff8a5c]/10"
                ></textarea>

                <!-- Character Counter -->
                <div class="mt-1 flex justify-end">
                    <span id="characterCounter" class="text-[11px] text-white/30">0 / 1000</span>
                </div>

                <!-- Proof Image Upload (NEW OPTIONAL FIELD) -->
                <div class="mt-4 mb-2">
                    <label for="proofImage" class="mb-2 block text-sm font-medium text-white/80">Upload Proof Image (Bill, Old Photo) <span class="text-white/40 text-xs">(Optional)</span></label>
                    <input type="file" id="proofImage" name="proof_image" accept="image/jpeg, image/png, image/jpg" class="block w-full text-sm text-white/70 file:mr-4 file:rounded-xl file:border-0 file:bg-[#ff8a5c]/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#ff8a5c] hover:file:bg-[#ff8a5c]/20">
                </div>

                <!-- Validation / API Message -->
                <div id="verificationMessage" class="mt-3 hidden rounded-xl border px-4 py-3 text-xs leading-relaxed"></div>

                <!-- Buttons -->
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row">
                    <button
                        id="cancelVerificationButton"
                        type="button"
                        class="flex-1 rounded-xl border border-white/10 bg-transparent px-5 py-3 font-semibold text-white/70 transition hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Cancel
                    </button>

                    <button
                        id="submitProofButton"
                        type="submit"
                        class="flex-1 rounded-xl bg-[#ff8a5c] px-5 py-3 font-semibold text-[#1a1511] transition hover:bg-[#ff9a72] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Submit Proof
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================
     JAVASCRIPT FOR MODAL
     ========================================================= -->
<script>
(function() {
    // DOM REFERENCES
    const modalOverlay = document.getElementById('modalOverlay');
    const matchDetailsModal = document.getElementById('matchDetailsModal');
    const verificationModal = document.getElementById('verificationModal');
    const closeMatchButton = document.getElementById('closeMatchButton');
    const closeVerificationButton = document.getElementById('closeVerificationButton');
    const claimItemButton = document.getElementById('claimItemButton');
    const rejectMatchButton = document.getElementById('rejectMatchButton');
    const cancelVerificationButton = document.getElementById('cancelVerificationButton');
    const verificationForm = document.getElementById('verificationForm');
    const proofText = document.getElementById('proofText');
    const proofImage = document.getElementById('proofImage'); // Added reference for the image
    const characterCounter = document.getElementById('characterCounter');
    const verificationMessage = document.getElementById('verificationMessage');

    // CURRENT MATCH
    let currentMatchData = null;

    // OPEN MATCH DETAILS (Call this function from your dashboard buttons)
    function openMatchModal(matchData) {
        currentMatchData = matchData;
        window.openMatchModal = openMatchModal;

        // Basic item information
        document.getElementById('matchItemImage').src = matchData.metadata?.image_url || '';
        document.getElementById('matchItemImage').alt = matchData.metadata?.title || 'Matched item';
        document.getElementById('matchCategory').textContent = matchData.category || 'ITEM';
        document.getElementById('matchReportId').textContent = matchData.report_id || '';
        document.getElementById('matchTitle').textContent = matchData.metadata?.title || 'Matched Item';
        document.getElementById('matchLocation').textContent = matchData.metadata?.location || 'Location unavailable';
        document.getElementById('matchDate').textContent = matchData.metadata?.date_reported || 'Date unavailable';

        // AI scores
        const aiReasons = matchData.ai_reasons || {};
        const visualScore = clampScore(aiReasons.visual_match_confidence);
        const dnaScore = clampScore(aiReasons.digital_dna_similarity);

        document.getElementById('visualScore').textContent = `${visualScore}%`;
        document.getElementById('dnaScore').textContent = `${dnaScore}%`;

        document.getElementById('visualProgress').style.width = '0%';
        document.getElementById('dnaProgress').style.width = '0%';

        requestAnimationFrame(() => {
            document.getElementById('visualProgress').style.width = `${visualScore}%`;
            document.getElementById('dnaProgress').style.width = `${dnaScore}%`;
        });

        // Safe Match Reasons
        renderSafeMatchReasons(aiReasons.safe_match_reasons);

        // Reset UI
        resetMatchButtons();
        clearVerificationState();

        // Show Modal A
        verificationModal.classList.add('hidden');
        matchDetailsModal.classList.remove('hidden');
        modalOverlay.classList.remove('hidden');
        modalOverlay.classList.add('flex');
        modalOverlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }

    function renderSafeMatchReasons(safeReasons) {
        const container = document.getElementById('safeMatchReasons');
        container.innerHTML = '';
        if (!safeReasons) return;

        const reasonDefinitions = [
            { key: 'category', text: 'Category information is consistent' },
            { key: 'visual_similarity', text: 'Strong visual similarity detected' },
            { key: 'multiple_attributes', text: 'Multiple item characteristics are consistent' }
        ];

        reasonDefinitions.forEach(reason => {
            if (safeReasons[reason.key] !== true) return;
            const li = document.createElement('li');
            li.className = 'flex items-start gap-2 text-sm leading-relaxed text-white/60';
            
            const check = document.createElement('span');
            check.className = 'mt-0.5 font-semibold text-green-400';
            check.textContent = '✓';
            
            const text = document.createElement('span');
            text.textContent = reason.text;
            
            li.appendChild(check);
            li.appendChild(text);
            container.appendChild(li);
        });
    }

    function clampScore(value) {
        const number = Number(value);
        if (!Number.isFinite(number)) return 0;
        return Math.min(100, Math.max(0, Math.round(number * 10) / 10));
    }

    // BUTTON LISTENERS
    claimItemButton.addEventListener('click', () => {
        if (!currentMatchData) return;
        matchDetailsModal.classList.add('hidden');
        clearVerificationState();
        verificationModal.classList.remove('hidden');
        setTimeout(() => { proofText.focus(); }, 100);
    });

    cancelVerificationButton.addEventListener('click', () => {
        verificationModal.classList.add('hidden');
        matchDetailsModal.classList.remove('hidden');
        clearVerificationState();
        claimItemButton.focus();
    });

    closeMatchButton.addEventListener('click', closeAllModals);

    closeVerificationButton.addEventListener('click', () => {
        verificationModal.classList.add('hidden');
        matchDetailsModal.classList.remove('hidden');
        clearVerificationState();
    });

    // REJECT MATCH
    rejectMatchButton.addEventListener('click', rejectCurrentMatch);

    async function rejectCurrentMatch() {
        if (!currentMatchData || rejectMatchButton.disabled) return;
        setRejectLoading(true);

        try {
            const response = await fetch('api/reject_match.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ match_id: currentMatchData.match_id })
            });

            let data;
            try { data = await response.json(); } catch { throw new Error('Invalid server response.'); }
            if (!response.ok || data.success !== true) {
                throw new Error(data.message || 'Failed to reject this match.');
            }

            currentMatchData.status = 'rejected';
            rejectMatchButton.textContent = 'Match Rejected ✓';
            claimItemButton.disabled = true;

            setTimeout(() => { closeAllModals(); }, 700);

        } catch (error) {
            setRejectLoading(false);
            alert(error.message || 'Unable to reject the match.');
        }
    }

    // SUBMIT PROOF
    verificationForm.addEventListener('submit', submitOwnerProof);

    async function submitOwnerProof(event) {
        event.preventDefault();
        if (!currentMatchData) return;

        const proofValue = proofText.value.trim();

        if (!proofValue) {
            showVerificationMessage('Please enter your ownership proof.', 'error');
            proofText.focus();
            return;
        }

        if (proofValue.length < 10) {
            showVerificationMessage('Please provide more detail. Your proof must contain at least 10 characters.', 'error');
            proofText.focus();
            return;
        }

        if (proofValue.length > 1000) {
            showVerificationMessage('Ownership proof cannot exceed 1000 characters.', 'error');
            proofText.focus();
            return;
        }

        setVerificationLoading(true);

        try {
            // USING FORMDATA INSTEAD OF URLSEARCHPARAMS TO SUPPORT IMAGE UPLOAD
            const formData = new FormData(verificationForm);
            formData.append('match_id', currentMatchData.match_id);

            const response = await fetch('api/verify_owner.php', {
                method: 'POST',
                body: formData // No headers required, browser sets multipart/form-data automatically
            });

            let data;
            try { data = await response.json(); } catch { throw new Error('Invalid server response.'); }
            
            if (!response.ok || data.success !== true) {
                throw new Error(data.message || 'Failed to submit ownership proof.');
            }

            currentMatchData.status = 'pending_founder_approval';
            showVerificationMessage(data.message || 'Proof submitted successfully. Please wait for founder review.', 'success');

            proofText.disabled = true;
            if (proofImage) proofImage.disabled = true; // Disable image input after submit
            submitProofButton.disabled = true;
            cancelVerificationButton.disabled = false;
            submitProofButton.textContent = 'Proof Submitted ✓';

        } catch (error) {
            showVerificationMessage(error.message || 'Something went wrong. Please try again.', 'error');
            setVerificationLoading(false);
        }
    }

    proofText.addEventListener('input', updateCharacterCounter);
    function updateCharacterCounter() {
        characterCounter.textContent = `${proofText.value.length} / 1000`;
    }

    // UI HELPERS
    function setVerificationLoading(loading) {
        submitProofButton.disabled = loading;
        cancelVerificationButton.disabled = loading;
        proofText.disabled = loading;
        if (proofImage) proofImage.disabled = loading; // Handle image loading state
        
        if (loading) {
            submitProofButton.innerHTML = `<span class="inline-flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-[#1a1511]/30 border-t-[#1a1511]"></span> Processing...</span>`;
        } else {
            submitProofButton.textContent = 'Submit Proof';
        }
    }

    function setRejectLoading(loading) {
        rejectMatchButton.disabled = loading;
        claimItemButton.disabled = loading;
        if (loading) {
            rejectMatchButton.innerHTML = `<span class="inline-flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-red-400/30 border-t-red-400"></span> Processing...</span>`;
        } else {
            rejectMatchButton.textContent = 'Reject Match';
        }
    }

    function resetMatchButtons() {
        rejectMatchButton.disabled = false;
        claimItemButton.disabled = false;
        rejectMatchButton.textContent = 'Reject Match';
        claimItemButton.textContent = 'Claim Item →';
    }

    function showVerificationMessage(message, type) {
        verificationMessage.className = 'mt-3 rounded-xl border px-4 py-3 text-xs leading-relaxed';
        if (type === 'success') {
            verificationMessage.classList.add('border-green-400/30', 'bg-green-400/10', 'text-green-300');
        } else {
            verificationMessage.classList.add('border-red-400/30', 'bg-red-400/10', 'text-red-300');
        }
        verificationMessage.textContent = message;
        verificationMessage.classList.remove('hidden');
    }

    function clearVerificationState() {
        proofText.value = '';
        proofText.disabled = false;
        if (proofImage) {
            proofImage.value = ''; // Reset the file input
            proofImage.disabled = false;
        }
        updateCharacterCounter();
        verificationMessage.className = 'mt-3 hidden rounded-xl border px-4 py-3 text-xs leading-relaxed';
        verificationMessage.textContent = '';
        submitProofButton.disabled = false;
        cancelVerificationButton.disabled = false;
        submitProofButton.textContent = 'Submit Proof';
    }

    function closeAllModals() {
        modalOverlay.classList.add('hidden');
        modalOverlay.classList.remove('flex');
        matchDetailsModal.classList.add('hidden');
        verificationModal.classList.add('hidden');
        modalOverlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        currentMatchData = null;
        clearVerificationState();
    }

    modalOverlay.addEventListener('click', event => {
        if (event.target !== modalOverlay) return;
        if (!verificationModal.classList.contains('hidden')) {
            verificationModal.classList.add('hidden');
            matchDetailsModal.classList.remove('hidden');
            clearVerificationState();
            return;
        }
        closeAllModals();
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || modalOverlay.classList.contains('hidden')) return;
        if (!verificationModal.classList.contains('hidden')) {
            verificationModal.classList.add('hidden');
            matchDetailsModal.classList.remove('hidden');
            clearVerificationState();
        } else {
            closeAllModals();
        }
    });

    window.openMatchModal = openMatchModal;
    window.closeMatchVerificationModal = closeAllModals;
})();
</script>