import unittest
from unittest.mock import MagicMock

# Import config and custom client classes
import config
from config import FailoverClient


class TestGeminiFailover(unittest.TestCase):

    def setUp(self):
        # Save original clients list
        self.original_clients = list(config.clients)

    def tearDown(self):
        # Restore original clients list
        config.clients = self.original_clients

    def test_key_failover_first(self):
        """Should try the best model across all keys before trying fallback models."""
        client1 = MagicMock()
        client2 = MagicMock()
        config.clients = [client1, client2]

        # Mock client1 to fail on gemini-2.5-flash
        client1.models.generate_content.side_effect = Exception("429 Resource Exhausted")

        # Mock client2 to succeed on gemini-2.5-flash
        mock_resp = MagicMock()
        mock_resp.text = "Success from client 2"
        client2.models.generate_content.return_value = mock_resp

        failover_client = FailoverClient()
        response = failover_client.models.generate_content(
            model="gemini-2.5-flash",
            contents="test prompt"
        )

        # Assertions
        self.assertEqual(response.text, "Success from client 2")
        client1.models.generate_content.assert_called_with(
            model="gemini-2.5-flash", contents="test prompt", config=None
        )
        client2.models.generate_content.assert_called_with(
            model="gemini-2.5-flash", contents="test prompt", config=None
        )

    def test_model_fallback_when_keys_exhausted(self):
        """Should fall back to the next standard model if the first model fails on all keys."""
        client1 = MagicMock()
        client2 = MagicMock()
        config.clients = [client1, client2]

        # Mock both clients to fail on gemini-2.5-flash
        def side_effect(model, contents, config=None, **kwargs):
            if model == "gemini-2.5-flash":
                raise Exception("429 Resource Exhausted")
            mock_resp = MagicMock()
            mock_resp.text = f"Success on model {model}"
            return mock_resp

        client1.models.generate_content.side_effect = side_effect
        client2.models.generate_content.side_effect = side_effect

        failover_client = FailoverClient()
        response = failover_client.models.generate_content(
            model="gemini-2.5-flash",
            contents="test prompt"
        )

        # Should fall back to the next standard model (gemini-flash-latest) on client 1
        self.assertEqual(response.text, "Success on model gemini-flash-latest")


if __name__ == "__main__":
    unittest.main()
