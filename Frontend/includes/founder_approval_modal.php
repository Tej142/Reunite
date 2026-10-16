<!-- =========================================================
     FOUNDER APPROVAL MODAL OVERLAY
     ========================================================= -->
<style>
#founderApprovalOverlay {
  display: none !important;
  position: fixed !important;
  inset: 0 !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
  z-index: 99999 !important;
  background: rgba(0, 0, 0, 0.78) !important;
  backdrop-filter: blur(8px) !important;
  -webkit-backdrop-filter: blur(8px) !important;
  padding: 1rem !important;
  overflow-y: auto !important;
  align-items: center !important;
  justify-content: center !important;
  box-sizing: border-box !important;
}
#founderApprovalOverlay.flex:not(.hidden),
#founderApprovalOverlay[aria-hidden="false"]:not(.hidden) {
  display: flex !important;
}
#founderApprovalOverlay.hidden,
#founderApprovalOverlay .hidden {
  display: none !important;
}
#founderApprovalModal {
  position: relative !important;
  width: 100% !important;
  max-width: 520px !important;
  background: var(--card-bg, #1a1511) !important;
  color: var(--text, #ffffff) !important;
  border: 1px solid rgba(255, 255, 255, 0.12) !important;
  border-radius: 18px !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6) !important;
  max-height: 90vh !important;
  overflow-y: auto !important;
  margin: auto !important;
}
</style>
<div id="founderApprovalOverlay"
     class="fixed inset-0 z-[110] hidden items-center justify-center bg-black bg-opacity-70 backdrop-blur-sm px-4 py-6"
     aria-hidden="true">

    <div id="founderApprovalModal"
         class="relative w-full max-w-lg rounded-2xl border border-white/10 bg-[#1a1511] shadow-2xl"
         role="dialog"
         aria-modal="true"
         aria-labelledby="founderApprovalTitle">

        <!-- Close Button -->
        <button id="closeFounderApprovalBtn"
                type="button"
                class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-full bg-white/5 text-2xl text-white/60 transition hover:bg-white/10 hover:text-white"
                aria-label="Close founder approval modal">
            &times;
        </button>

        <div class="p-6 sm:p-7">

            <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-[#ff8a5c]/10 text-xl">
                👀
            </div>

            <h2 id="founderApprovalTitle" class="text-2xl font-bold text-white mb-2">
                Review Ownership Proof
            </h2>

            <p class="text-sm text-white/55 mb-6">
                Review the details provided by the claimant to verify if they are the true owner.
            </p>

            <!-- PROOF DETAILS -->
            <div class="space-y-4 mb-4">

                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-white/50 mb-2">
                        Proof Description
                    </h3>

                    <div id="displayProofText"
                         class="w-full rounded-xl border border-white/10 bg-[#241d18] px-4 py-3 text-sm text-white/80 whitespace-pre-wrap min-h-[80px]"></div>
                </div>

                <div id="proofImageContainer" class="hidden">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-white/50 mb-2">
                        Attached Image
                    </h3>

                    <div class="rounded-xl overflow-hidden border border-white/10 bg-[#241d18] flex justify-center p-2">
                        <img id="displayProofImage"
                             src=""
                             alt="Ownership proof attachment"
                             class="max-h-48 rounded-lg object-contain">
                    </div>
                </div>

            </div>

            <!-- SHARED FEEDBACK -->
            <div id="approvalMessage"
                 class="hidden mb-4 rounded-xl border px-4 py-3 text-xs leading-relaxed"
                 role="status"
                 aria-live="polite"></div>

            <!-- INITIAL ACTION BUTTONS -->
            <div id="initialActionButtons" class="flex flex-col sm:flex-row gap-3">

                <button id="btnRejectClaim"
                        type="button"
                        class="flex-1 rounded-xl border border-white/10 bg-transparent px-5 py-3 font-semibold text-white/70 transition hover:bg-red-400/10 hover:text-red-400 hover:border-red-400/50">
                    Reject Claim
                </button>

                <button id="btnApproveClaim"
                        type="button"
                        class="flex-1 rounded-xl bg-[#ff8a5c] px-5 py-3 font-semibold text-[#1a1511] transition hover:bg-[#ff9a72]">
                    Accept &amp; Return Item
                </button>

            </div>

            <!-- HANDOVER DETAILS -->
            <form id="handoverForm" class="hidden mt-2 space-y-4" novalidate>

                <hr class="border-white/10 my-4">

                <h3 class="text-sm font-semibold text-white">
                    How will you return the item?
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                    <label class="relative flex cursor-pointer rounded-xl border border-white/10 bg-[#241d18] p-4 hover:border-[#ff8a5c]/50">
                        <input type="radio"
                               name="return_type"
                               value="hand_on_hand"
                               class="peer sr-only"
                               required>

                        <div class="text-sm font-medium text-white peer-checked:text-[#ff8a5c]">
                            🤝 Meet in Person
                        </div>

                        <div class="absolute inset-0 rounded-xl border-2 border-transparent peer-checked:border-[#ff8a5c] pointer-events-none transition-all"></div>
                    </label>

                    <label class="relative flex cursor-pointer rounded-xl border border-white/10 bg-[#241d18] p-4 hover:border-[#ff8a5c]/50">
                        <input type="radio"
                               name="return_type"
                               value="admin_office"
                               class="peer sr-only">

                        <div class="text-sm font-medium text-white peer-checked:text-[#ff8a5c]">
                            🏢 Drop at Admin
                        </div>

                        <div class="absolute inset-0 rounded-xl border-2 border-transparent peer-checked:border-[#ff8a5c] pointer-events-none transition-all"></div>
                    </label>

                </div>

                <!-- IN-PERSON MEETING FIELDS -->
                <div id="handOnHandDetails"
                     class="hidden space-y-4 bg-[#241d18]/50 p-4 rounded-xl mt-3 border border-white/5">

                    <div>
                        <label for="meetLocation"
                               class="mb-1 block text-xs font-medium text-white/70">
                            Meeting Location
                        </label>

                        <input type="text"
                               name="meet_location"
                               id="meetLocation"
                               maxlength="255"
                               placeholder="e.g. Mainblock Library Lobby"
                               class="w-full rounded-xl border border-white/10 bg-[#1a1511] px-4 py-3 text-sm text-white outline-none placeholder:text-white/25 focus:border-[#ff8a5c]/60 focus:ring-2 focus:ring-[#ff8a5c]/10">
                    </div>

                    <div>
                        <label for="meetTime"
                               class="mb-1 block text-xs font-medium text-white/70">
                            Meeting Date &amp; Time
                        </label>

                        <input type="datetime-local"
                               name="meet_time"
                               id="meetTime"
                               class="w-full rounded-xl border border-white/10 bg-[#1a1511] px-4 py-3 text-sm text-white outline-none placeholder:text-white/25 focus:border-[#ff8a5c]/60 focus:ring-2 focus:ring-[#ff8a5c]/10">
                    </div>

                </div>

                <!-- API CONTRACT FIELDS -->
                <input type="hidden" name="match_id" id="approvalMatchId">
                <input type="hidden" name="action" id="approvalAction" value="approve">
                <input type="hidden" name="csrf_token" id="founderCsrfToken" value="">

                <div class="flex gap-3 mt-6">

                    <button type="button"
                            id="btnCancelLogistics"
                            class="flex-1 rounded-xl border border-white/10 bg-transparent px-4 py-3 text-sm font-semibold text-white/70 transition hover:bg-white/5 hover:text-white">
                        Back
                    </button>

                    <button type="submit"
                            id="btnConfirmApproval"
                            class="flex-1 rounded-xl bg-[#ff8a5c] px-4 py-3 text-sm font-semibold text-[#1a1511] transition hover:bg-[#ff9a72]">
                        Confirm Approval
                    </button>

                </div>

            </form>

        </div>
    </div>
</div>

<script>
(function() {
    const founderApprovalOverlay = document.getElementById('founderApprovalOverlay');
    const closeFounderApprovalBtn = document.getElementById('closeFounderApprovalBtn');

    const displayProofText = document.getElementById('displayProofText');
    const displayProofImage = document.getElementById('displayProofImage');
    const proofImageContainer = document.getElementById('proofImageContainer');

    const initialActionButtons = document.getElementById('initialActionButtons');
    const handoverForm = document.getElementById('handoverForm');
    const handOnHandDetails = document.getElementById('handOnHandDetails');

    const approvalMatchId = document.getElementById('approvalMatchId');
    const approvalAction = document.getElementById('approvalAction');
    const founderCsrfToken = document.getElementById('founderCsrfToken');
    const approvalMessage = document.getElementById('approvalMessage');

    const btnApproveClaim = document.getElementById('btnApproveClaim');
    const btnRejectClaim = document.getElementById('btnRejectClaim');
    const btnConfirmApproval = document.getElementById('btnConfirmApproval');
    const btnCancelLogistics = document.getElementById('btnCancelLogistics');

    const meetLocationInput = document.getElementById('meetLocation');
    const meetTimeInput = document.getElementById('meetTime');

    // 1. Strict CSRF Source: PHP Session Only
    const pageCsrfToken = <?= json_encode(
        $_SESSION['csrf_token'] ?? '',
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;

    // Set minimum meeting date/time using the browser's local time.
    function setMinDateTime() {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());

        meetTimeInput.min = now.toISOString().slice(0, 16);
    }

    // Enable/disable interactive controls while a request is processing.
    function setFormLoadingState(isLoading) {
        btnApproveClaim.disabled = isLoading;
        btnRejectClaim.disabled = isLoading;
        btnConfirmApproval.disabled = isLoading;
        btnCancelLogistics.disabled = isLoading;
        closeFounderApprovalBtn.disabled = isLoading;

        document.querySelectorAll('input[name="return_type"]').forEach(radio => {
            radio.disabled = isLoading;
        });

        meetLocationInput.disabled = isLoading;
        meetTimeInput.disabled = isLoading;

        if (isLoading) {
            btnConfirmApproval.innerHTML =
                '<span class="inline-flex items-center gap-2">' +
                    '<span class="h-4 w-4 animate-spin rounded-full border-2 border-[#1a1511]/30 border-t-[#1a1511]"></span>' +
                    'Processing...' +
                '</span>';

            btnRejectClaim.innerHTML =
                '<span class="inline-flex items-center gap-2">' +
                    '<span class="h-4 w-4 animate-spin rounded-full border-2 border-red-400/30 border-t-red-400"></span>' +
                    'Processing...' +
                '</span>';
        } else {
            btnConfirmApproval.textContent = 'Confirm Approval';
            btnRejectClaim.textContent = 'Reject Claim';
        }
    }

    function showFeedbackMessage(message, isSuccess) {
        approvalMessage.className = isSuccess
            ? 'mb-4 rounded-xl border border-green-400/30 bg-green-400/10 px-4 py-3 text-xs text-green-300 flex items-center gap-3'
            : 'mb-4 rounded-xl border border-[#4a1a1a] bg-[#2a1111] px-4 py-3 text-xs text-[#ff8a8a]';

        if (isSuccess && window.Orbi) {
            approvalMessage.innerHTML = '';
            const orbiEl = window.Orbi.create({ state: 'happy', size: 40, label: 'Success' });
            if (orbiEl) {
                approvalMessage.appendChild(orbiEl);
                window.Orbi.happy(orbiEl, 2500);
            }
            const msgSpan = document.createElement('span');
            msgSpan.textContent = message || 'Operation completed successfully.';
            approvalMessage.appendChild(msgSpan);
        } else {
            approvalMessage.textContent = message || (
                isSuccess ? 'Operation completed successfully.' : 'An error occurred.'
            );
        }

        approvalMessage.classList.remove('hidden');
    }

    // Keep the meeting fields synchronized with the selected return type.
    function syncReturnTypeFields() {
        const selected = document.querySelector(
            'input[name="return_type"]:checked'
        );

        const isInPerson = selected?.value === 'hand_on_hand';

        handOnHandDetails.classList.toggle('hidden', !isInPerson);

        meetLocationInput.required = isInPerson;
        meetTimeInput.required = isInPerson;

        if (!isInPerson) {
            meetLocationInput.value = '';
            meetTimeInput.value = '';
        }
    }

    // Update the UI whenever the return type changes.
    document.querySelectorAll('input[name="return_type"]').forEach(radio => {
        radio.addEventListener('change', syncReturnTypeFields);
    });

    // 2. Open the modal with validated data.
    function openFounderApprovalModal(matchId, proofText, proofImagePath = null) {
        // Strictly validate the match ID.
        const parsedMatchId = Number(matchId);

        if (!Number.isSafeInteger(parsedMatchId) || parsedMatchId <= 0) {
            console.error('Invalid Match ID provided to founder approval modal.');
            return;
        }

        // Reset the form BEFORE setting hidden values.
        handoverForm.reset();

        approvalMatchId.value = String(parsedMatchId);
        founderCsrfToken.value = pageCsrfToken;
        approvalAction.value = 'approve';

        meetLocationInput.required = false;
        meetTimeInput.required = false;

        displayProofText.textContent = proofText ?? '';

        // Allow trusted proof-upload paths (media_vault/proofs or uploads/proofs).
        const normalizedImagePath =
            typeof proofImagePath === 'string' ? proofImagePath.trim() : '';

        const isTrustedImagePath =
            (normalizedImagePath.startsWith('uploads/proofs/') ||
             normalizedImagePath.startsWith('media_vault/proofs/') ||
             normalizedImagePath.startsWith('../media_vault/proofs/')) &&
            !normalizedImagePath.includes('\\') &&
            !normalizedImagePath.startsWith('//');

        if (isTrustedImagePath) {
            let displaySrc = normalizedImagePath;
            if (displaySrc.startsWith('media_vault/')) {
                displaySrc = '../' + displaySrc;
            }
            proofImageContainer.classList.remove('hidden');
            displayProofImage.src = displaySrc;
        } else {
            proofImageContainer.classList.add('hidden');
            displayProofImage.removeAttribute('src');
        }

        setMinDateTime();
        setFormLoadingState(false);

        initialActionButtons.classList.remove('hidden');
        handoverForm.classList.add('hidden');
        handOnHandDetails.classList.add('hidden');
        approvalMessage.classList.add('hidden');

        founderApprovalOverlay.setAttribute('aria-hidden', 'false');
        founderApprovalOverlay.classList.remove('hidden');
        founderApprovalOverlay.classList.add('flex');
    }

    // Hide the image section if an allowed path does not load.
    displayProofImage.addEventListener('error', () => {
        proofImageContainer.classList.add('hidden');
        displayProofImage.removeAttribute('src');
    });

    // Close the modal.
    closeFounderApprovalBtn.addEventListener('click', closeFounderModal);

    function closeFounderModal() {
        if (closeFounderApprovalBtn.disabled) {
            return;
        }

        founderApprovalOverlay.setAttribute('aria-hidden', 'true');
        founderApprovalOverlay.classList.add('hidden');
        founderApprovalOverlay.classList.remove('flex');
    }

    // 3. Back button with return-type state synchronization.
    btnCancelLogistics.addEventListener('click', () => {
        handoverForm.classList.add('hidden');
        initialActionButtons.classList.remove('hidden');
        approvalMessage.classList.add('hidden');

        const selected = document.querySelector(
            'input[name="return_type"]:checked'
        );

        const isInPerson = selected?.value === 'hand_on_hand';

        handOnHandDetails.classList.toggle('hidden', !isInPerson);

        meetLocationInput.required = isInPerson;
        meetTimeInput.required = isInPerson;
    });

    // REJECT CLAIM
    btnRejectClaim.addEventListener('click', async () => {
        if (!approvalMatchId.value) {
            showFeedbackMessage('Invalid match ID.', false);
            return;
        }

        if (!pageCsrfToken) {
            showFeedbackMessage('Security token missing. Refresh the page and try again.', false);
            return;
        }

        const confirmed = confirm(
            'Are you sure you want to reject this claim? The claimant will be notified.'
        );

        if (!confirmed) {
            return;
        }

        // Capture request values before changing loading state.
        const formData = new FormData();
        formData.append('match_id', approvalMatchId.value);
        formData.append('action', 'reject');
        formData.append('csrf_token', pageCsrfToken);

        setFormLoadingState(true);
        approvalMessage.classList.add('hidden');

        try {
            const response = await fetch('api/founder_approval.php', {
                method: 'POST',
                body: formData
            });

            let data = null;

            try {
                data = await response.json();
            } catch (parseError) {
                // The response was not valid JSON.
            }

            if (!response.ok || !data || !data.success) {
                throw new Error(
                    data?.message || 'Failed to communicate with the server.'
                );
            }

            showFeedbackMessage(
                data.message || 'Claim rejected successfully.',
                true
            );

            // Keep controls disabled until the page reloads.
            setTimeout(() => {
                location.reload();
            }, 1500);

        } catch (error) {
            showFeedbackMessage(
                error.message || 'A server error occurred.',
                false
            );

            setFormLoadingState(false);
        }
    });

    // ACCEPT CLAIM -> SHOW HANDOVER FORM
    btnApproveClaim.addEventListener('click', () => {
        initialActionButtons.classList.add('hidden');
        handoverForm.classList.remove('hidden');
        approvalMessage.classList.add('hidden');

        syncReturnTypeFields();
    });

    // SUBMIT APPROVAL / HANDOVER DETAILS
    handoverForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!pageCsrfToken) {
            showFeedbackMessage(
                'Security token missing. Refresh the page and try again.',
                false
            );
            return;
        }

        // Refresh the minimum time immediately before validation.
        setMinDateTime();

        if (!handoverForm.checkValidity()) {
            handoverForm.reportValidity();
            return;
        }

        // Important: Create FormData BEFORE disabling the form controls.
        const formData = new FormData(handoverForm);

        setFormLoadingState(true);
        approvalMessage.classList.add('hidden');

        try {
            const response = await fetch('api/founder_approval.php', {
                method: 'POST',
                body: formData
            });

            let data = null;

            try {
                data = await response.json();
            } catch (parseError) {
                // The response was not valid JSON.
            }

            if (!response.ok || !data || !data.success) {
                throw new Error(
                    data?.message || 'Server error occurred during approval.'
                );
            }

            showFeedbackMessage(
                data.message || 'Claim approved successfully.',
                true
            );

            if (window.ReuniteToast) {
                window.ReuniteToast.show({
                    title: 'Claim Approved!',
                    message: data.message || 'Handover details logged successfully.',
                    type: 'success',
                    orbiState: 'happy',
                    duration: 3500
                });
            }

            // Keep controls disabled until the page reloads.
            setTimeout(() => {
                location.reload();
            }, 1500);

        } catch (error) {
            showFeedbackMessage(
                error.message || 'An error occurred during approval.',
                false
            );

            setFormLoadingState(false);
        }
    });

    window.openFounderApprovalModal = openFounderApprovalModal;
    window.closeFounderApprovalModal = closeFounderApprovalModal;
})();
</script>