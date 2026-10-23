"""Own the suite's descendant processes and temporary files on the campaign host."""

import os
import signal
import subprocess
import tempfile


def stop_group(process):
    """Stop only the session group created for this invocation, including leftover children."""
    try:
        os.killpg(process.pid, signal.SIGKILL)
    except ProcessLookupError:
        pass


def run_process(command, cwd, environment, timeout):
    """Capture a suite while stopping its whole process group before removing its files."""
    with tempfile.TemporaryDirectory(prefix="low-yearly-suite-") as temporary:
        owned_environment = dict(environment)
        owned_environment["TMPDIR"] = temporary
        owned_environment["LOW_YEARLY_SUITE_TEMP"] = temporary
        process = subprocess.Popen(
            command,
            cwd=cwd,
            env=owned_environment,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            start_new_session=True,
        )
        try:
            try:
                stdout, stderr = process.communicate(timeout=timeout)
            except subprocess.TimeoutExpired:
                stop_group(process)
                stdout, stderr = process.communicate()
                raise subprocess.TimeoutExpired(command, timeout, output=stdout, stderr=stderr)
            return subprocess.CompletedProcess(command, process.returncode, stdout, stderr)
        finally:
            stop_group(process)
            process.wait()
