class TALKAIState:
    """
    Manages the state of a Talk-to-AI conversation.

    Responsibilities:
    - Store category and report type
    - Store Question Engine questions
    - Store user answers
    - Track answered / unanswered questions
    - Find the next unanswered required question
    - Prevent sensitive information from being stored
    - Decide whether the conversation is complete

    Gemini / Live API logic does NOT belong here.
    """

    # Fields that must never be collected or stored.
    BLOCKED_FIELD_KEYWORDS = {
        "password",
        "passcode",
        "pin",
        "pattern",
        "otp",
        "secret",
        "login_credential",
    }

    def __init__(self, category, report_type, questions=None):
        self.category = self._clean_text(category)
        self.report_type = self._clean_text(report_type)

        self.questions = questions or []
        self.answers = {}

        self.conversation_finished = False

    # =========================================================
    # Basic helpers
    # =========================================================

    @staticmethod
    def _clean_text(value):
        if value is None:
            return ""
        return str(value).strip()

    def _is_blocked_field(self, question_id):
        """
        Returns True if the question appears to request
        sensitive authentication information.
        """
        question_id = self._clean_text(question_id).lower()

        return any(
            keyword in question_id
            for keyword in self.BLOCKED_FIELD_KEYWORDS
        )

    # =========================================================
    # Question management
    # =========================================================

    def set_questions(self, questions):
        """
        Replace the current question set.

        Answers are preserved because questions may be refreshed
        without losing information already collected.
        """
        self.questions = questions or []
        self.conversation_finished = False

    def get_questions(self):
        """Return all questions."""
        return self.questions

    def get_question_by_id(self, question_id):
        """Find a question using its ID."""
        for question in self.questions:

            if self._get_question_id(question) == question_id:
                return question

        return None

    @staticmethod
    def _get_question_id(question):
        """
        Supports both:
        - Question dataclass objects
        - dictionary-based questions
        """

        if isinstance(question, dict):
            return question.get("id")

        return getattr(question, "id", None)

    @staticmethod
    def _is_required(question):
        """Check whether a question is required."""

        if isinstance(question, dict):
            return bool(question.get("required", False))

        return bool(getattr(question, "required", False))

    # =========================================================
    # Answer management
    # =========================================================

    def save_answer(self, question_id, answer):
        """
        Save or update an answer.

        Returns:
            True  -> answer successfully stored
            False -> answer rejected
        """

        question_id = self._clean_text(question_id)

        if not question_id:
            return False

        # Never store sensitive authentication information.
        if self._is_blocked_field(question_id):
            return False

        if answer is None:
            return False

        # Clean string answers.
        if isinstance(answer, str):
            answer = answer.strip()

            if not answer:
                return False

        self.answers[question_id] = answer

        # New answer means conversation may no longer be finished.
        self.conversation_finished = False

        return True

    def save_answers(self, answers):
        """
        Save multiple answers at once.

        Useful when Talk-to-AI extracts multiple pieces of
        information from one user message.
        """

        if not isinstance(answers, dict):
            return False

        saved_any = False

        for question_id, answer in answers.items():

            if self.save_answer(question_id, answer):
                saved_any = True

        return saved_any

    def get_answer(self, question_id):
        """Return the stored answer for a question."""
        return self.answers.get(question_id)

    def has_answer(self, question_id):
        """Check whether a valid answer has been stored."""

        return (
            question_id in self.answers
            and self.answers[question_id] not in (None, "")
        )

    # =========================================================
    # Required / unanswered questions
    # =========================================================

    def get_unanswered_required_questions(self):
        """
        Return all required questions that still need answers.
        """

        unanswered = []

        for question in self.questions:

            question_id = self._get_question_id(question)

            if not question_id:
                continue

            # Ignore blocked sensitive fields.
            if self._is_blocked_field(question_id):
                continue

            if not self._is_required(question):
                continue

            if not self.has_answer(question_id):
                unanswered.append(question)

        return unanswered

    def get_next_question(self):
        """
        Return the next required question that has not
        been answered yet.

        The conversation does NOT depend on a fixed index.
        """

        unanswered = self.get_unanswered_required_questions()

        if not unanswered:
            return None

        return unanswered[0]

    def is_complete(self):
        """
        Conversation is complete when every required,
        non-sensitive question has an answer.
        """

        return len(self.get_unanswered_required_questions()) == 0

    # =========================================================
    # Conversation state
    # =========================================================

    def finish(self):
        """
        Mark the conversation as finished only when all
        required questions are answered.
        """

        if self.is_complete():
            self.conversation_finished = True
            return True

        return False

    def is_finished(self):
        """Return current conversation completion state."""
        return self.conversation_finished

    # =========================================================
    # Collected data
    # =========================================================

    def get_collected_data(self):
        """
        Return the information collected during the conversation.
        """

        return {
            "category": self.category,
            "report_type": self.report_type,
            "answers": self.answers.copy()
        }

    # =========================================================
    # Reset
    # =========================================================

    def reset(self):
        """Reset the conversation answers and state."""

        self.answers.clear()
        self.conversation_finished = False