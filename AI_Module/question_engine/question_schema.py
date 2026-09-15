from dataclasses import dataclass, field
from typing import List, Literal


ImportanceLevel = Literal["highest", "high", "medium", "low"]

QuestionType = Literal[
    "text",
    "choice",
    "dropdown",
    "yes_no"
]


@dataclass
class Question:
    id: str
    question: str
    type: QuestionType
    choices: List[str] = field(default_factory=list)
    required: bool = True
    private: bool = False
    importance: ImportanceLevel = "medium"


@dataclass
class QuestionSet:
    category: str
    report_type: str
    version: int = 1
    questions: List[Question] = field(default_factory=list)