import unittest
from unittest.mock import patch

from normalizers.normalizer import normalize_report


class TestNormalizer(unittest.TestCase):

    # =========================================================
    # 1. DESCRIPTION INPUT
    # =========================================================

    @patch("normalizers.normalizer.analyze_report")
    def test_description_normalization(self, mock_analyzer):

        mock_analyzer.return_value = {
            "object_type": "mobile",
            "attributes": {
                "brand": "Samsung",
                "model": "Galaxy S24",
                "color": "Black",
                "storage": "256GB",
            },
            "location": "Tirupati Bus Stand",
            "visible_features": [
                {
                    "type": "scratch",
                    "value": "small scratch near camera",
                }
            ],
            "private_features": [
                {
                    "type": "purchase date",
                    "value": "May 2026",
                }
            ],
        }

        result = normalize_report(
            input_data="My black Samsung Galaxy S24 was lost at Tirupati Bus Stand.",
            source="description",
            category="mobile",
        )

        self.assertTrue(result["success"])

        data = result["data"]

        self.assertEqual(data["object_type"], "mobile")
        self.assertEqual(data["attributes"]["brand"], "Samsung")
        self.assertEqual(data["attributes"]["model"], "Galaxy S24")
        self.assertEqual(data["attributes"]["color"], "Black")
        self.assertEqual(data["location"], "Tirupati Bus Stand")

        self.assertEqual(len(data["visible_features"]), 1)
        self.assertEqual(len(data["private_features"]), 1)

        mock_analyzer.assert_called_once()


    # =========================================================
    # 2. DESCRIPTION EMPTY
    # =========================================================

    def test_empty_description(self):

        result = normalize_report(
            input_data="",
            source="description",
            category="mobile",
        )

        self.assertFalse(result["success"])
        self.assertEqual(
            result["error"],
            "Description cannot be empty.",
        )


    # =========================================================
    # 3. DESCRIPTION CATEGORY FALLBACK
    # =========================================================

    @patch("normalizers.normalizer.analyze_report")
    def test_description_category_fallback(self, mock_analyzer):

        mock_analyzer.return_value = {
            "object_type": "",
            "attributes": {
                "brand": "Apple",
            },
            "location": "",
            "visible_features": [],
            "private_features": [],
        }

        result = normalize_report(
            input_data="Apple device",
            source="description",
            category="mobile",
        )

        self.assertTrue(result["success"])
        self.assertEqual(
            result["data"]["object_type"],
            "mobile",
        )


    # =========================================================
    # 4. INSTANT CHOICE
    # =========================================================

    @patch("normalizers.normalizer.analyze_report")
    def test_instant_choice_normalization(self, mock_analyzer):

        questions = [
            {
                "id": "mobile_brand",
                "question": "What is the brand?",
                "type": "dropdown",
                "choices": ["Apple", "Samsung"],
                "required": True,
                "private": False,
                "importance": "high",
                "target": "attributes",
            },
            {
                "id": "mobile_model",
                "question": "What is the model?",
                "type": "text",
                "choices": [],
                "required": True,
                "private": False,
                "importance": "high",
                "target": "attributes",
            },
            {
                "id": "mobile_color",
                "question": "What is the color?",
                "type": "choice",
                "choices": ["Black", "White"],
                "required": True,
                "private": False,
                "importance": "medium",
                "target": "attributes",
            },
            {
                "id": "mobile_location",
                "question": "Where was it lost?",
                "type": "text",
                "choices": [],
                "required": True,
                "private": False,
                "importance": "medium",
                "target": "location",
            },
            {
                "id": "mobile_unique_mark",
                "question": "Any unique visible mark?",
                "type": "text",
                "choices": [],
                "required": False,
                "private": False,
                "importance": "highest",
                "target": "visible_features",
            },
            {
                "id": "mobile_purchase_date",
                "question": "When did you purchase it?",
                "type": "text",
                "choices": [],
                "required": False,
                "private": True,
                "importance": "highest",
                "target": "private_features",
            },
        ]

        mock_analyzer.return_value = {
            "object_type": "",
            "attributes": {},
            "location": "",
            "visible_features": [],
            "private_features": [],
        }

        input_data = {
            "answers": [
                {
                    "question_id": "mobile_brand",
                    "answer": "Samsung",
                },
                {
                    "question_id": "mobile_model",
                    "answer": "Galaxy S24",
                },
                {
                    "question_id": "mobile_color",
                    "answer": "Black",
                },
                {
                    "question_id": "mobile_location",
                    "answer": "Tirupati Bus Stand",
                },
                {
                    "question_id": "mobile_unique_mark",
                    "answer": "Small scratch near camera",
                },
                {
                    "question_id": "mobile_purchase_date",
                    "answer": "May 2026",
                },
            ]
        }

        result = normalize_report(
            input_data=input_data,
            source="instant_choice",
            category="mobile",
            questions=questions,
        )

        self.assertTrue(result["success"])

        data = result["data"]

        self.assertEqual(data["object_type"], "mobile")

        self.assertEqual(
            data["attributes"]["brand"],
            "Samsung",
        )

        self.assertEqual(
            data["attributes"]["model"],
            "Galaxy S24",
        )

        self.assertEqual(
            data["attributes"]["color"],
            "Black",
        )

        self.assertEqual(
            data["location"],
            "Tirupati Bus Stand",
        )

        self.assertEqual(
            data["visible_features"][0]["value"],
            "Small scratch near camera",
        )

        self.assertEqual(
            data["private_features"][0]["value"],
            "May 2026",
        )


    # =========================================================
    # 5. INSTANT CHOICE + ADDITIONAL TEXT
    # =========================================================

    @patch("normalizers.normalizer.analyze_report")
    def test_instant_choice_with_additional_text(
        self,
        mock_analyzer,
    ):

        questions = [
            {
                "id": "bag_brand",
                "question": "What is the brand?",
                "type": "text",
                "choices": [],
                "required": True,
                "private": False,
                "importance": "high",
                "target": "attributes",
            }
        ]

        mock_analyzer.return_value = {
            "object_type": "",
            "attributes": {
                "material": "Canvas",
            },
            "location": "",
            "visible_features": [
                {
                    "type": "zipper",
                    "value": "Yellow zipper pull",
                }
            ],
            "private_features": [],
        }

        input_data = {
            "answers": [
                {
                    "question_id": "bag_brand",
                    "answer": "Wildcraft",
                }
            ],
            "additional_text": "Canvas bag with yellow zipper pull.",
        }

        result = normalize_report(
            input_data=input_data,
            source="instant_choice",
            category="bag",
            questions=questions,
        )

        self.assertTrue(result["success"])

        data = result["data"]

        self.assertEqual(
            data["attributes"]["brand"],
            "Wildcraft",
        )

        self.assertEqual(
            data["attributes"]["material"],
            "Canvas",
        )

        self.assertEqual(
            data["visible_features"][0]["value"],
            "Yellow zipper pull",
        )

        mock_analyzer.assert_called_once()


    # =========================================================
    # 6. UNKNOWN QUESTION ID
    # =========================================================

    def test_unknown_question_id_is_ignored(self):

        questions = [
            {
                "id": "mobile_brand",
                "target": "attributes",
                "private": False,
            }
        ]

        input_data = {
            "answers": [
                {
                    "question_id": "mobile_unknown",
                    "answer": "Something",
                },
                {
                    "question_id": "mobile_brand",
                    "answer": "Samsung",
                },
            ]
        }

        result = normalize_report(
            input_data=input_data,
            source="instant_choice",
            category="mobile",
            questions=questions,
        )

        self.assertTrue(result["success"])

        data = result["data"]

        self.assertEqual(
            data["attributes"]["brand"],
            "Samsung",
        )

        self.assertNotIn(
            "mobile_unknown",
            data["attributes"],
        )


    # =========================================================
    # 7. BLOCK PASSWORD / PIN
    # =========================================================

    def test_sensitive_fields_are_blocked(self):

        questions = [
            {
                "id": "mobile_password",
                "target": "private_features",
                "private": True,
            },
            {
                "id": "mobile_pin",
                "target": "private_features",
                "private": True,
            },
            {
                "id": "mobile_brand",
                "target": "attributes",
                "private": False,
            },
        ]

        input_data = {
            "answers": [
                {
                    "question_id": "mobile_password",
                    "answer": "mypassword",
                },
                {
                    "question_id": "mobile_pin",
                    "answer": "1234",
                },
                {
                    "question_id": "mobile_brand",
                    "answer": "Samsung",
                },
            ]
        }

        result = normalize_report(
            input_data=input_data,
            source="instant_choice",
            category="mobile",
            questions=questions,
        )

        self.assertTrue(result["success"])

        data = result["data"]

        self.assertEqual(
            data["attributes"]["brand"],
            "Samsung",
        )

        self.assertEqual(
            data["private_features"],
            [],
        )


    # =========================================================
    # 8. DUPLICATE FEATURES
    # =========================================================

    def test_duplicate_features_are_removed(self):

        questions = [
            {
                "id": "bag_mark",
                "target": "visible_features",
                "private": False,
            }
        ]

        input_data = {
            "answers": [
                {
                    "question_id": "bag_mark",
                    "answer": "Blue sticker",
                },
                {
                    "question_id": "bag_mark",
                    "answer": "Blue sticker",
                },
            ]
        }

        result = normalize_report(
            input_data=input_data,
            source="instant_choice",
            category="bag",
            questions=questions,
        )

        self.assertTrue(result["success"])

        self.assertEqual(
            len(result["data"]["visible_features"]),
            1,
        )


    # =========================================================
    # 9. TALK TO AI
    # =========================================================

    @patch("normalizers.normalizer.ResponseParser")
    def test_talk_to_ai_normalization(self, mock_parser_class):

        questions = [
            {
                "id": "laptop_brand",
                "target": "attributes",
                "private": False,
            },
            {
                "id": "laptop_model",
                "target": "attributes",
                "private": False,
            },
            {
                "id": "laptop_location",
                "target": "location",
                "private": False,
            },
            {
                "id": "laptop_mark",
                "target": "visible_features",
                "private": False,
            },
        ]

        mock_parser = mock_parser_class.return_value

        mock_parser.parse.return_value = {
            "success": True,
            "answers": {
                "laptop_brand": "Dell",
                "laptop_model": "Inspiron 15",
                "laptop_location": "College",
                "laptop_mark": "Scratch near the left corner",
            },
        }

        input_data = {
            "answers": {
                "laptop_brand": "Dell",
                "laptop_model": "Inspiron 15",
                "laptop_location": "College",
                "laptop_mark": "Scratch near the left corner",
            }
        }

        result = normalize_report(
            input_data=input_data,
            source="talk_to_ai",
            category="laptop",
            questions=questions,
        )

        self.assertTrue(result["success"])

        data = result["data"]

        self.assertEqual(
            data["object_type"],
            "laptop",
        )

        self.assertEqual(
            data["attributes"]["brand"],
            "Dell",
        )

        self.assertEqual(
            data["attributes"]["model"],
            "Inspiron 15",
        )

        self.assertEqual(
            data["location"],
            "College",
        )

        self.assertEqual(
            data["visible_features"][0]["value"],
            "Scratch near the left corner",
        )

        mock_parser.parse.assert_called_once_with(
            input_data
        )


    # =========================================================
    # 10. INVALID SOURCE
    # =========================================================

    def test_invalid_source(self):

        result = normalize_report(
            input_data="test",
            source="random_source",
        )

        self.assertFalse(result["success"])


    # =========================================================
    # 11. OUTPUT STRUCTURE
    # =========================================================

    @patch("normalizers.normalizer.analyze_report")
    def test_final_output_structure(self, mock_analyzer):

        mock_analyzer.return_value = {
            "object_type": "mobile",
            "attributes": {
                "brand": "Apple",
            },
            "location": "Tirupati",
            "visible_features": [],
            "private_features": [],
        }

        result = normalize_report(
            input_data="Apple mobile",
            source="description",
            category="mobile",
        )

        self.assertTrue(result["success"])

        data = result["data"]

        expected_keys = {
            "object_type",
            "attributes",
            "location",
            "visible_features",
            "private_features",
        }

        self.assertEqual(
            set(data.keys()),
            expected_keys,
        )


if __name__ == "__main__":
    unittest.main(verbosity=2)