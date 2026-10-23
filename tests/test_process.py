"""Verify outer suite cleanup when a native descendant outlives its parent."""

import importlib.util
import os
from pathlib import Path
import signal
import subprocess
import sys
import tempfile
import time
import unittest

spec = importlib.util.spec_from_file_location("low_yearly_process", Path(__file__).parent / "support/process.py")
process_module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(process_module)


class ProcessOwnershipTest(unittest.TestCase):
    """Use short owned processes and an external heartbeat to detect surviving work."""

    def run_descendant(self, parent_waits):
        """Launch a child that writes until the helper stops its process group."""
        with tempfile.TemporaryDirectory(prefix="low-yearly-process-test-") as observation:
            observation = Path(observation)
            child = """
import os,time
from pathlib import Path
root=Path(os.environ['REVIEW_OBSERVATION'])
(root/'child.pid').write_text(str(os.getpid()))
(root/'temporary').write_text(os.environ['TMPDIR'])
Path(os.environ['TMPDIR'],'native-output').write_text('owned')
for index in range(200):
    (root/'heartbeat').write_text(str(index))
    time.sleep(.02)
"""
            parent = """
import os,subprocess,sys,time
from pathlib import Path
subprocess.Popen([sys.executable,'-c',os.environ['REVIEW_CHILD']],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
marker=Path(os.environ['REVIEW_OBSERVATION'],'heartbeat')
deadline=time.monotonic()+2
while not marker.exists():
    if time.monotonic()>deadline:raise RuntimeError('Child did not start')
    time.sleep(.01)
print('parent started',flush=True)
if os.environ['REVIEW_PARENT_WAITS']=='1':time.sleep(4)
"""
            environment = dict(os.environ, REVIEW_OBSERVATION=str(observation), REVIEW_CHILD=child, REVIEW_PARENT_WAITS="1" if parent_waits else "0")
            started = time.monotonic()
            try:
                command = [sys.executable, "-c", parent]
                if parent_waits:
                    with self.assertRaises(subprocess.TimeoutExpired):
                        process_module.run_process(command, str(observation), environment, 1)
                else:
                    result = process_module.run_process(command, str(observation), environment, 3)
                    self.assertEqual(0, result.returncode, result.stderr)
                    self.assertEqual("parent started\n", result.stdout)
                self.assertLess(time.monotonic() - started, 3)
                self.assertTrue((observation / "heartbeat").is_file())
                heartbeat = (observation / "heartbeat").read_text()
                time.sleep(.1)
                self.assertEqual(heartbeat, (observation / "heartbeat").read_text())
                self.assertFalse(Path((observation / "temporary").read_text()).exists())
            finally:
                pid_file = observation / "child.pid"
                if pid_file.exists():
                    try:
                        os.kill(int(pid_file.read_text()), signal.SIGKILL)
                    except ProcessLookupError:
                        pass

    def test_timeout_stops_child_and_removes_output(self):
        """A suite deadline must stop descendants before deleting their owned files."""
        self.run_descendant(True)

    def test_normal_parent_exit_stops_leftover_child(self):
        """A successful suite must not leave a child working after its parent exits."""
        self.run_descendant(False)


if __name__ == "__main__":
    unittest.main()
