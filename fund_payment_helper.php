<?php
// fund_payment_helper.php

function applyCapturedFundPayment(
    mysqli $conn,
    string $orderId,
    string $paymentId,
    int $amountPaise
): bool {
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            "SELECT donation_id, request_id, amount,
                    payment_status, gateway_payment_id
             FROM fund_donations
             WHERE gateway_order_id = ?
             FOR UPDATE"
        );
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $donation = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$donation) {
            throw new Exception('No donation matches this payment order.');
        }

        $expectedPaise = (int)round((float)$donation['amount'] * 100);

        if ($expectedPaise !== $amountPaise) {
            throw new Exception('Payment amount does not match the donation.');
        }

        // Idempotency: a repeated confirmation must not count twice.
        if (strtolower($donation['payment_status']) === 'paid') {
            if ($donation['gateway_payment_id'] === $paymentId) {
                $conn->commit();
                return true;
            }
            throw new Exception('This donation is already linked to another payment.');
        }

        $stmt = $conn->prepare(
            "SELECT amount, donated_amount
             FROM fund_requests
             WHERE request_id = ?
             FOR UPDATE"
        );
        $stmt->bind_param('i', $donation['request_id']);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request) {
            throw new Exception('Fund request was not found.');
        }

        $newTotal = round(
            (float)$request['donated_amount'] + (float)$donation['amount'],
            2
        );
        $requestTotal = (float)$request['amount'];
        $newStatus = ($newTotal >= $requestTotal) ? 'completed' : 'approved';

        $paidStatus = 'paid';

        // gateway_payment_id has a unique index to prevent reuse.
        $stmt = $conn->prepare(
            "UPDATE fund_donations
             SET gateway_payment_id = ?,
                 transaction_ref = ?,
                 payment_status = ?
             WHERE donation_id = ? AND payment_status <> 'paid'"
        );
        $stmt->bind_param(
            'sssi',
            $paymentId,
            $paymentId,
            $paidStatus,
            $donation['donation_id']
        );
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new Exception('Donation payment state changed; please retry.');
        }
        $stmt->close();

        $stmt = $conn->prepare(
            "UPDATE fund_requests
             SET donated_amount = ?, status = ?
             WHERE request_id = ?"
        );
        $stmt->bind_param(
            'dsi',
            $newTotal,
            $newStatus,
            $donation['request_id']
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        return true;

    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}