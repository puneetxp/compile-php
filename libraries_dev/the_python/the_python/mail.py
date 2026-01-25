"""Simple SMTP mail helper for generated projects."""
from __future__ import annotations

import os
import smtplib
from email.message import EmailMessage
from typing import Iterable

__all__ = ["Mailer"]


class Mailer:
    def __init__(self) -> None:
        self.host = os.getenv("MAIL_HOST", "localhost")
        self.port = int(os.getenv("MAIL_PORT", "25"))
        self.username = os.getenv("MAIL_USERNAME")
        self.password = os.getenv("MAIL_PASSWORD")
        self.default_from = os.getenv("MAIL_FROM", "noreply@example.com")
        self.use_tls = os.getenv("MAIL_TLS", "false").lower() in {"1", "true", "yes"}

    def send(self, subject: str, body: str, to: Iterable[str], *, sender: str | None = None) -> None:
        message = EmailMessage()
        message["Subject"] = subject
        message["From"] = sender or self.default_from
        message["To"] = ", ".join(to)
        message.set_content(body)

        with smtplib.SMTP(self.host, self.port) as smtp:
            if self.use_tls:
                smtp.starttls()
            if self.username and self.password:
                smtp.login(self.username, self.password)
            smtp.send_message(message)
